<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    private const DEFAULT_PRODUCT_IMAGE = 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=80';

    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function products(): JsonResponse
    {
        $products = DB::table('products')->orderBy('rowid')->get()
            ->map(fn (object $product): array => $this->productArray($product, false));

        return response()->json($products);
    }

    public function register(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');

        if ($name === '' || $email === '' || $password === '') {
            return response()->json(['message' => 'Name, email, and password are required.'], 400);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'A valid email address is required.'], 400);
        }

        if (DB::table('customers')->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json(['message' => 'This email is already registered.'], 409);
        }

        $user = [
            'id' => 'cust-'.Str::uuid(),
            'name' => $name,
            'email' => $email,
            'role' => 'customer',
        ];

        DB::table('customers')->insert([
            ...$user,
            'password' => Hash::make($password),
            'phone' => trim((string) $request->input('phone', '')),
            'createdAt' => now()->toIso8601String(),
        ]);

        return response()->json([
            'token' => $this->issueToken($user),
            'user' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            return response()->json(['message' => 'Email and password are required.'], 400);
        }

        if (
            hash_equals(strtolower((string) config('access.admin_email')), $email)
            && hash_equals((string) config('access.admin_password'), $password)
        ) {
            $user = [
                'id' => 'admin-1',
                'name' => 'Owner Admin',
                'email' => $email,
                'role' => 'admin',
            ];

            return response()->json(['token' => $this->issueToken($user), 'user' => $user]);
        }

        $customer = DB::table('customers')->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $customer || ! $this->verifyAndUpgradePassword($customer, $password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        $user = [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'role' => 'customer',
        ];

        DB::table('visits')->insert([
            'id' => (string) Str::uuid(),
            'path' => '/login',
            'page' => 'Customer login',
            'customerId' => $customer->id,
            'createdAt' => now()->toIso8601String(),
        ]);

        return response()->json(['token' => $this->issueToken($user), 'user' => $user]);
    }

    public function adminSummary(Request $request): JsonResponse
    {
        if (($denied = $this->requireRole($request, 'admin', 'Admin access required.')) !== null) {
            return $denied;
        }

        $products = DB::table('products')->orderBy('rowid')->get();
        $orders = DB::table('orders')->orderByDesc('rowid');
        $customers = DB::table('customers')->orderBy('rowid')->get()
            ->map(fn (object $customer): array => $this->safeCustomer($customer));

        return response()->json([
            'summary' => [
                'totalRevenue' => (float) DB::table('orders')->sum('total'),
                'totalOrders' => DB::table('orders')->count(),
                'totalItemsSold' => (int) $products->sum('sold'),
                'lowStock' => $products->where('stock', '<=', 8)->count(),
            ],
            'products' => $products->map(fn (object $product): array => $this->productArray($product, true)),
            'customers' => $customers,
            'visits' => DB::table('visits')->orderByDesc('rowid')->limit(10)->get(),
            'orders' => $orders->limit(5)->get()->map(fn (object $order): array => $this->orderArray($order)),
        ]);
    }

    public function createProduct(Request $request): JsonResponse
    {
        if (($denied = $this->requireRole($request, 'admin', 'Admin access required.')) !== null) {
            return $denied;
        }

        $name = trim((string) $request->input('name', ''));
        $category = trim((string) $request->input('category', ''));
        $price = $request->input('price');
        $stock = $request->input('stock');

        if ($name === '' || $category === '') {
            return response()->json(['message' => 'Product name and category are required.'], 400);
        }
        if (! is_numeric($price) || ! is_finite((float) $price) || (float) $price <= 0) {
            return response()->json(['message' => 'Price must be a positive number.'], 400);
        }
        if (! is_numeric($stock) || (int) $stock != $stock || (int) $stock < 0) {
            return response()->json(['message' => 'Stock must be a non-negative whole number.'], 400);
        }

        $product = [
            'id' => 'prod-'.Str::uuid(),
            'name' => $name,
            'category' => $category,
            'price' => (float) $price,
            'stock' => (int) $stock,
            'sold' => 0,
            'image' => trim((string) $request->input('image', '')) ?: self::DEFAULT_PRODUCT_IMAGE,
            'description' => trim((string) $request->input('description', '')),
            'featured' => 0,
        ];

        DB::table('products')->insert($product);

        return response()->json([
            'message' => 'Product created successfully.',
            'product' => $this->productArray((object) $product, true),
        ], 201);
    }

    public function messages(Request $request): JsonResponse
    {
        $user = $request->attributes->get('authUser');
        $query = DB::table('messages');

        if ($user->role !== 'admin') {
            $query->where(function ($messages) use ($user): void {
                $messages->where('customerId', $user->id)->orWhere('sender', 'admin');
            });
        }

        return response()->json($query->orderByDesc('rowid')->limit(20)->get());
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $user = $request->attributes->get('authUser');
        $message = trim((string) $request->input('message', ''));

        if ($message === '') {
            return response()->json(['message' => 'Message text is required.'], 400);
        }

        $newMessage = [
            'id' => (string) Str::uuid(),
            'customerId' => $user->role === 'customer' ? $user->id : 'admin-1',
            'sender' => $user->role,
            'name' => $user->name,
            'message' => $message,
            'createdAt' => now()->toIso8601String(),
        ];

        DB::table('messages')->insert($newMessage);

        return response()->json(['message' => $newMessage], 201);
    }

    public function feedback(): JsonResponse
    {
        return response()->json(DB::table('feedback')->orderByDesc('rowid')->limit(10)->get());
    }

    public function submitFeedback(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('name', ''));
        $message = trim((string) $request->input('message', ''));
        $rating = $request->input('rating');

        if ($name === '' || ! $rating || $message === '') {
            return response()->json(['message' => 'Name, rating, and message are required.'], 400);
        }
        if (! is_numeric($rating) || (int) $rating != $rating || (int) $rating < 1 || (int) $rating > 5) {
            return response()->json(['message' => 'Rating must be a whole number from 1 to 5.'], 400);
        }

        DB::table('feedback')->insert([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'rating' => (int) $rating,
            'message' => $message,
            'createdAt' => now()->toIso8601String(),
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function createOrder(Request $request): JsonResponse
    {
        $user = $request->attributes->get('authUser');
        if (($denied = $this->requireRole($request, 'customer', 'Only customers can create orders.')) !== null) {
            return $denied;
        }

        $items = $request->input('items');
        if (! is_array($items) || $items === []) {
            return response()->json(['message' => 'Your cart is empty.'], 400);
        }

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['productId'], $item['quantity'])
                || ! is_numeric($item['quantity'])
                || (int) $item['quantity'] != $item['quantity']
                || (int) $item['quantity'] <= 0) {
                return response()->json(['message' => 'Each item must have a positive quantity.'], 400);
            }
        }

        $paymentMethod = $request->input('paymentMethod') ?: 'ABA Mobile';
        $shippingAddress = $request->input('shippingAddress') ?: 'Cambodia';

        try {
            $order = DB::transaction(function () use ($items, $user, $paymentMethod, $shippingAddress): array {
                $orderItems = [];
                $total = 0.0;
                $productsToUpdate = [];

                foreach ($items as $item) {
                    $product = DB::table('products')->where('id', $item['productId'])->first();
                    if (! $product) {
                        throw new \DomainException("Product {$item['productId']} was not found.");
                    }

                    $quantity = (int) $item['quantity'];
                    if ((int) $product->stock < $quantity) {
                        throw new \DomainException("Only {$product->stock} units left for {$product->name}.");
                    }

                    $productsToUpdate[] = ['id' => $product->id, 'quantity' => $quantity];
                    $orderItems[] = [
                        'productId' => $product->id,
                        'name' => $product->name,
                        'quantity' => $quantity,
                        'price' => (float) $product->price,
                    ];
                    $total += (float) $product->price * $quantity;
                }

                foreach ($productsToUpdate as $product) {
                    $updated = DB::table('products')
                        ->where('id', $product['id'])
                        ->where('stock', '>=', $product['quantity'])
                        ->decrement('stock', $product['quantity'], [
                            'sold' => DB::raw('sold + '.$product['quantity']),
                        ]);

                    if ($updated !== 1) {
                        throw new \RuntimeException('Product stock changed during checkout. Please try again.');
                    }
                }

                $order = [
                    'id' => 'order-'.substr((string) Str::uuid(), 0, 8),
                    'customerId' => $user->id,
                    'customerName' => $user->name,
                    'items' => $orderItems,
                    'total' => $total,
                    'paymentMethod' => $paymentMethod,
                    'shippingAddress' => $shippingAddress,
                    'status' => 'paid',
                    'createdAt' => now()->toIso8601String(),
                ];

                DB::table('orders')->insert([
                    ...$order,
                    'items' => json_encode($orderItems, JSON_THROW_ON_ERROR),
                ]);

                return $order;
            });
        } catch (\DomainException $exception) {
            $status = str_ends_with($exception->getMessage(), 'was not found.') ? 404 : 400;

            return response()->json(['message' => $exception->getMessage()], $status);
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'Product stock changed during checkout. Please try again.') {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            throw $exception;
        }

        return response()->json(['message' => 'Order created successfully.', 'order' => $order], 201);
    }

    public function customerOrders(Request $request): JsonResponse
    {
        if (($denied = $this->requireRole($request, 'customer', 'Customer access required.')) !== null) {
            return $denied;
        }

        $user = $request->attributes->get('authUser');
        $orders = DB::table('orders')->where('customerId', $user->id)->orderBy('rowid')->get()
            ->map(fn (object $order): array => $this->orderArray($order));

        return response()->json($orders);
    }

    public function updateStock(Request $request, string $id): JsonResponse
    {
        if (($denied = $this->requireRole($request, 'admin', 'Admin access required.')) !== null) {
            return $denied;
        }

        $stock = $request->input('stock');
        if (! is_numeric($stock) || (int) $stock != $stock || (int) $stock < 0) {
            return response()->json(['message' => 'Stock must be a non-negative whole number.'], 400);
        }

        $product = DB::table('products')->where('id', $id)->first();
        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        DB::table('products')->where('id', $id)->update(['stock' => (int) $stock]);

        return response()->json([
            'message' => 'Stock updated',
            'product' => $this->productArray((object) [...(array) $product, 'stock' => (int) $stock], true),
        ]);
    }

    private function issueToken(array $user): string
    {
        $token = bin2hex(random_bytes(40));

        DB::table('api_tokens')->insert([
            'id' => (string) Str::uuid(),
            'token' => hash('sha256', $token),
            'user' => json_encode($user, JSON_THROW_ON_ERROR),
            'createdAt' => now()->toIso8601String(),
        ]);

        return $token;
    }

    private function verifyAndUpgradePassword(object $customer, string $password): bool
    {
        $passwordInfo = password_get_info($customer->password);
        $isHashed = ($passwordInfo['algoName'] ?? 'unknown') !== 'unknown';
        $valid = $isHashed
            ? Hash::check($password, $customer->password)
            : hash_equals($customer->password, $password);

        if ($valid && ! $isHashed) {
            DB::table('customers')->where('id', $customer->id)->update([
                'password' => Hash::make($password),
            ]);
        }

        return $valid;
    }

    private function requireRole(Request $request, string $role, string $message): ?JsonResponse
    {
        return $request->attributes->get('authUser')->role === $role
            ? null
            : response()->json(['message' => $message], 403);
    }

    private function productArray(object $product, bool $includeSold): array
    {
        $result = [
            'id' => $product->id,
            'name' => $product->name,
            'category' => $product->category,
            'price' => (float) $product->price,
            'stock' => (int) $product->stock,
            'image' => $product->image,
            'description' => $product->description,
            'featured' => (bool) $product->featured,
        ];

        if ($includeSold) {
            $result['sold'] = (int) $product->sold;
        }

        return $result;
    }

    private function safeCustomer(object $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'role' => $customer->role,
            'createdAt' => $customer->createdAt,
        ];
    }

    private function orderArray(object $order): array
    {
        return [
            'id' => $order->id,
            'customerId' => $order->customerId,
            'customerName' => $order->customerName,
            'items' => json_decode($order->items, true, flags: JSON_THROW_ON_ERROR),
            'total' => (float) $order->total,
            'paymentMethod' => $order->paymentMethod,
            'shippingAddress' => $order->shippingAddress,
            'status' => $order->status,
            'createdAt' => $order->createdAt,
        ];
    }
}
