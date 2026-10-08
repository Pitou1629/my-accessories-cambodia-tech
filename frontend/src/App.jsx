import { useEffect, useMemo, useState } from 'react';

const API_URL = '/api';

const currency = (value) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0
  }).format(value);

const khr = (value) => `${new Intl.NumberFormat('en-US').format(value)}៛`;

function getStoredUser() {
  try {
    const stored = localStorage.getItem('macUser');
    return stored ? JSON.parse(stored) : null;
  } catch {
    return null;
  }
}

async function apiFetch(path, options = {}) {
  const token = localStorage.getItem('macToken');
  const requestHeaders = {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(options.headers || {})
  };

  const request = {
    ...options,
    headers: requestHeaders
  };

  if (options.body !== undefined) {
    request.body = JSON.stringify(options.body);
  }

  const response = await fetch(`${API_URL}${path}`, request);
  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(data.message || 'Request failed');
  }

  return data;
}

function App() {
  const [user, setUser] = useState(() => getStoredUser());
  const [products, setProducts] = useState([]);
  const [cart, setCart] = useState([]);
  const [feedback, setFeedback] = useState([]);
  const [authMode, setAuthMode] = useState('login');
  const [authForm, setAuthForm] = useState({
    name: '',
    email: '',
    password: '',
    phone: ''
  });
  const [status, setStatus] = useState('');
  const [summary, setSummary] = useState(null);
  const [customerOrders, setCustomerOrders] = useState([]);
  const [messages, setMessages] = useState([]);
  const isAdmin = user?.role === 'admin';
  const [checkoutForm, setCheckoutForm] = useState({
    paymentMethod: 'ABA Mobile',
    shippingAddress: 'Phnom Penh, Cambodia'
  });

  const totalCartValue = useMemo(
    () => cart.reduce((sum, item) => sum + item.price * item.quantity, 0),
    [cart]
  );

  const loadProducts = async () => {
    try {
      const data = await apiFetch('/products');
      setProducts(data);
    } catch (error) {
      setStatus(error.message);
    }
  };

  const loadFeedback = async () => {
    try {
      const data = await apiFetch('/feedback');
      setFeedback(data);
    } catch (error) {
      setStatus(error.message);
    }
  };

  const loadSummary = async () => {
    try {
      const data = await apiFetch('/admin/summary');
      setSummary(data);
    } catch (error) {
      console.error(error);
    }
  };

  const loadCustomerOrders = async () => {
    try {
      const data = await apiFetch('/customer/orders');
      setCustomerOrders(data);
    } catch (error) {
      console.error(error);
    }
  };

  const loadMessages = async () => {
    try {
      const data = await apiFetch('/chat');
      setMessages(data);
    } catch (error) {
      console.error(error);
    }
  };

  useEffect(() => {
    loadProducts();
    loadFeedback();
  }, []);

  useEffect(() => {
    if (user?.role === 'admin') {
      loadSummary();
      loadMessages();
    }

    if (user?.role === 'customer') {
      loadCustomerOrders();
      loadMessages();
    }
  }, [user]);

  const handleAuthChange = (field, value) => {
    setAuthForm((current) => ({ ...current, [field]: value }));
  };

  const handleAuthSubmit = async (event) => {
    event.preventDefault();
    setStatus('');

    try {
      const endpoint = authMode === 'login' ? '/auth/login' : '/auth/register';
      const payload =
        authMode === 'login'
          ? { email: authForm.email, password: authForm.password }
          : {
              name: authForm.name,
              email: authForm.email,
              password: authForm.password,
              phone: authForm.phone
            };

      const result = await apiFetch(endpoint, { method: 'POST', body: payload });
      localStorage.setItem('macToken', result.token);
      localStorage.setItem('macUser', JSON.stringify(result.user));
      setUser(result.user);
      setStatus(authMode === 'login' ? `Welcome back, ${result.user.name}!` : 'Account created successfully.');
      setAuthForm({ name: '', email: '', password: '', phone: '' });
    } catch (error) {
      setStatus(error.message);
    }
  };

  const handleLogout = () => {
    localStorage.removeItem('macToken');
    localStorage.removeItem('macUser');
    setUser(null);
    setCart([]);
    setStatus('You have signed out.');
  };

  const addToCart = (product) => {
    setCart((current) => {
      const existing = current.find((item) => item.id === product.id);
      if (existing) {
        return current.map((item) =>
          item.id === product.id ? { ...item, quantity: item.quantity + 1 } : item
        );
      }
      return [...current, { ...product, quantity: 1 }];
    });
    setStatus(`${product.name} added to cart.`);
  };

  const updateQuantity = (productId, change) => {
    setCart((current) =>
      current
        .map((item) =>
          item.id === productId ? { ...item, quantity: Math.max(0, item.quantity + change) } : item
        )
        .filter((item) => item.quantity > 0)
    );
  };

  const submitOrder = async () => {
    if (!user) {
      setStatus('Please log in before checking out.');
      return;
    }

    if (cart.length === 0) {
      setStatus('Your cart is empty.');
      return;
    }

    try {
      const payload = {
        items: cart.map((item) => ({ productId: item.id, quantity: item.quantity })),
        paymentMethod: checkoutForm.paymentMethod,
        shippingAddress: checkoutForm.shippingAddress
      };

      const response = await apiFetch('/orders', { method: 'POST', body: payload });
      setStatus(`Order ${response.order.id} placed successfully.`);
      setCart([]);
      loadProducts();
      loadCustomerOrders();
    } catch (error) {
      setStatus(error.message);
    }
  };

  const handleFeedbackSubmit = async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const payload = {
      name: form.get('name'),
      rating: Number(form.get('rating')),
      message: form.get('message')
    };

    try {
      await apiFetch('/feedback', { method: 'POST', body: payload });
      setStatus('Thank you for your feedback.');
      event.currentTarget.reset();
      loadFeedback();
    } catch (error) {
      setStatus(error.message);
    }
  };

  const handleChatSubmit = async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const message = form.get('message');
    if (!message || !String(message).trim()) {
      setStatus('Please enter a message before sending.');
      return;
    }

    try {
      await apiFetch('/chat', { method: 'POST', body: { message } });
      event.currentTarget.reset();
      loadMessages();
      setStatus('Message sent to the owner.');
    } catch (error) {
      setStatus(error.message);
    }
  };

  const handleProductSubmit = async (event) => {
    event.preventDefault();
    const productForm = event.currentTarget;
    const form = new FormData(productForm);
    const payload = {
      name: form.get('name'),
      category: form.get('category'),
      price: Number(form.get('price')),
      stock: Number(form.get('stock')),
      image: form.get('image'),
      description: form.get('description')
    };

    try {
      await apiFetch('/admin/products', { method: 'POST', body: payload });
      productForm.reset();
      setStatus('Product added successfully.');
      await loadSummary();
      await loadProducts();
    } catch (error) {
      setStatus(error.message);
    }
  };

  const adminProducts = summary?.products || [];
  const maxSold = adminProducts.reduce((max, item) => Math.max(max, item.sold || 0), 1);

  return (
    <div className="app-shell">
      <header className="topbar">
        <div className="brand-wrap">
          <div className="brand-mark">M</div>
          <div>
            <p className="brand-name">My Accessories Cambodia Tech</p>
            <span className="brand-subtitle">Mobile accessories & smart devices</span>
          </div>
        </div>

        <nav className="main-nav">
          {isAdmin ? (
            <a href="#admin">Dashboard</a>
          ) : (
            <>
              <a href="#shop">Shop</a>
              <a href="#about">About</a>
              <a href="#reviews">Reviews</a>
              <a href="#support">Support</a>
            </>
          )}
        </nav>

        <div className="nav-actions">
          {user ? (
            <>
              <span className="user-pill">{user.name}</span>
              <button className="secondary-btn" onClick={handleLogout}>Logout</button>
            </>
          ) : (
            <button className="primary-btn" onClick={() => window.scrollTo({ top: 300, behavior: 'smooth' })}>
              Login / Register
            </button>
          )}
        </div>
      </header>

      <main className="container">
        {!isAdmin && <section className="hero">
          <div className="hero-copy">
            <span className="eyebrow">Best mobile accessories in Cambodia</span>
            <h1>Power your phone with premium accessories built for daily life.</h1>
            <p>
              Find fast chargers, protection cases, earbuds, power banks, and smart accessories
              trusted by customers across Phnom Penh and beyond.
            </p>
            <div className="cta-row">
              <a href="#shop" className="primary-btn">Shop Now</a>
              <a href="#support" className="secondary-btn">Chat With Owner</a>
            </div>
            <div className="stats-row">
              <div><strong>{products.length}</strong><span>Accessories</span></div>
              <div><strong>Local</strong><span>Cambodia delivery</span></div>
              <div><strong>24/7</strong><span>Support</span></div>
            </div>
          </div>

          <div className="hero-card panel">
            <div className="mini-card">
              <span className="mini-label">Shop with confidence</span>
              <h3>Everyday tech, made simple</h3>
              <p>Quality accessories, friendly local support.</p>
            </div>
            <div className="mini-meta">
              <span>Serving</span>
              <strong>Phnom Penh & beyond</strong>
            </div>
          </div>
        </section>}

        {status && <div className="status-banner">{status}</div>}

        {!user && (
          <section className="auth-section panel" id="login">
            <div className="auth-copy">
              <span className="eyebrow">Customer access</span>
              <h2>Register or login to get exclusive offers and faster checkout.</h2>
              <ul>
                <li>Track your orders</li>
                <li>Save your payment details</li>
                <li>Receive product support from the owner</li>
              </ul>
            </div>

            <form className="auth-form" onSubmit={handleAuthSubmit}>
              <div className="auth-tabs">
                <button type="button" className={authMode === 'login' ? 'active' : ''} onClick={() => setAuthMode('login')}>
                  Login
                </button>
                <button type="button" className={authMode === 'register' ? 'active' : ''} onClick={() => setAuthMode('register')}>
                  Register
                </button>
              </div>

              {authMode === 'register' && (
                <label>
                  Full Name
                  <input
                    type="text"
                    value={authForm.name}
                    onChange={(event) => handleAuthChange('name', event.target.value)}
                    placeholder="Your name"
                    required
                  />
                </label>
              )}

              <label>
                Email
                <input
                  type="email"
                  value={authForm.email}
                  onChange={(event) => handleAuthChange('email', event.target.value)}
                  placeholder="you@example.com"
                  required
                />
              </label>

              {authMode === 'register' && (
                <label>
                  Phone
                  <input
                    type="tel"
                    value={authForm.phone}
                    onChange={(event) => handleAuthChange('phone', event.target.value)}
                    placeholder="+855 12 345 678"
                  />
                </label>
              )}

              <label>
                Password
                <input
                  type="password"
                  value={authForm.password}
                  onChange={(event) => handleAuthChange('password', event.target.value)}
                  placeholder="••••••••"
                  required
                />
              </label>

              <button className="primary-btn submit-btn" type="submit">
                {authMode === 'login' ? 'Login' : 'Create Account'}
              </button>
            </form>
          </section>
        )}

        {user?.role === 'admin' && summary && (
          <section className="admin-panel panel" id="admin">
            <div className="panel-header">
              <div>
                <span className="eyebrow">Admin dashboard</span>
                <h2>Store overview and analytics</h2>
              </div>
            </div>

            <div className="metric-grid">
              <div className="metric-card">
                <span>Total Revenue</span>
                <strong>{currency(summary.summary.totalRevenue / 1000)}K</strong>
              </div>
              <div className="metric-card">
                <span>Total Orders</span>
                <strong>{summary.summary.totalOrders}</strong>
              </div>
              <div className="metric-card">
                <span>Total Items Sold</span>
                <strong>{summary.summary.totalItemsSold}</strong>
              </div>
              <div className="metric-card">
                <span>Customers</span>
                <strong>{summary.customers.length}</strong>
              </div>
              <div className="metric-card warning">
                <span>Low Stock Items</span>
                <strong>{summary.summary.lowStock}</strong>
              </div>
            </div>

            <div className="panel inner-panel admin-product-panel">
              <div className="panel-header">
                <div>
                  <span className="eyebrow">Inventory</span>
                  <h3>Add a product</h3>
                </div>
              </div>
              <form className="admin-product-form" onSubmit={handleProductSubmit}>
                <label>
                  Product name
                  <input type="text" name="name" required />
                </label>
                <label>
                  Category
                  <input type="text" name="category" required />
                </label>
                <label>
                  Price (៛)
                  <input type="number" name="price" min="1" step="1" required />
                </label>
                <label>
                  Stock
                  <input type="number" name="stock" min="0" step="1" required />
                </label>
                <label className="wide-field">
                  Image URL
                  <input type="url" name="image" placeholder="Optional" />
                </label>
                <label className="wide-field">
                  Description
                  <textarea name="description" rows="3" />
                </label>
                <button className="primary-btn" type="submit">Add product</button>
              </form>
            </div>

            <div className="analytics-grid">
              <div className="panel inner-panel">
                <h3>Sold-by-product</h3>
                <div className="chart-wrap">
                  {adminProducts.map((product) => (
                    <div key={product.id} className="product-chart-row">
                      <span>{product.name}</span>
                      <div className="chart-bar-shell">
                        <div className="chart-bar" style={{ width: `${(product.sold / maxSold) * 100}%` }} />
                      </div>
                      <strong>{product.sold}</strong>
                    </div>
                  ))}
                </div>
              </div>

              <div className="panel inner-panel">
                <h3>Customer logins</h3>
                <div className="visit-list">
                  {summary.visits.map((visit) => (
                    <div key={visit.id} className="list-row">
                      <span>{visit.page}</span>
                      <small>{new Date(visit.createdAt).toLocaleString()}</small>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="table-wrap">
              <h3>Inventory control</h3>
              <table>
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Sold</th>
                  </tr>
                </thead>
                <tbody>
                  {adminProducts.map((product) => (
                    <tr key={product.id} className={product.stock <= 8 ? 'low-stock' : ''}>
                      <td>{product.name}</td>
                      <td>{khr(product.price)}</td>
                      <td>
                        <input
                          type="number"
                          min="0"
                          value={product.stock}
                          onChange={async (event) => {
                            try {
                              await apiFetch(`/admin/products/${product.id}/stock`, {
                                method: 'PATCH',
                                body: { stock: Number(event.target.value) }
                              });
                              loadSummary();
                              loadProducts();
                            } catch (error) {
                              setStatus(error.message);
                            }
                          }}
                        />
                      </td>
                      <td>{product.sold}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="panel inner-panel admin-messages">
              <div className="panel-header">
                <div>
                  <span className="eyebrow">Customer support</span>
                  <h3>Messages</h3>
                </div>
              </div>
              <div className="message-list">
                {messages.map((message) => (
                  <div key={message.id} className={`message-row ${message.sender}`}>
                    <strong>{message.name}</strong>
                    <p>{message.message}</p>
                  </div>
                ))}
              </div>
              <form className="chat-form" onSubmit={handleChatSubmit}>
                <input type="text" name="message" placeholder="Reply to a customer..." />
                <button type="submit" className="primary-btn">Send</button>
              </form>
            </div>
          </section>
        )}

        {!isAdmin && <section className="shop-layout" id="shop">
          <div className="catalog-panel panel">
            <div className="panel-header shop-header">
              <div>
                <span className="eyebrow">Featured products</span>
                <h2>Best accessories for every mobile lifestyle</h2>
              </div>
              <span className="pill">{products.length} items</span>
            </div>

            <div className="product-grid">
              {products.map((product) => (
                <article key={product.id} className="product-card">
                  <img src={product.image} alt={product.name} />
                  <div className="product-body">
                    <div className="product-topline">
                      <span>{product.category}</span>
                      <span className={product.stock <= 8 ? 'danger-text' : ''}>Stock: {product.stock}</span>
                    </div>
                    <h3>{product.name}</h3>
                    <p>{product.description}</p>
                    <div className="product-footer">
                      <div>
                        <strong>{khr(product.price)}</strong>
                      </div>
                      <button className="secondary-btn" onClick={() => addToCart(product)}>
                        Add to cart
                      </button>
                    </div>
                  </div>
                </article>
              ))}
            </div>
          </div>

          <aside className="cart-panel panel">
            <div className="panel-header">
              <div>
                <span className="eyebrow">Cart</span>
                <h3>Your selected items</h3>
              </div>
            </div>

            {cart.length === 0 ? (
              <div className="empty-state">
                <p>Your cart is empty. Add a few accessories to continue.</p>
              </div>
            ) : (
              <div className="cart-list">
                {cart.map((item) => (
                  <div key={item.id} className="cart-item">
                    <div>
                      <strong>{item.name}</strong>
                      <small>{khr(item.price)} each</small>
                    </div>
                    <div className="quantity-control">
                      <button type="button" onClick={() => updateQuantity(item.id, -1)}>-</button>
                      <span>{item.quantity}</span>
                      <button type="button" onClick={() => updateQuantity(item.id, 1)}>+</button>
                    </div>
                  </div>
                ))}
              </div>
            )}

            <div className="checkout-box">
              <div className="checkout-row">
                <span>Subtotal</span>
                <strong>{khr(totalCartValue)}</strong>
              </div>
              <label>
                Payment method
                <select
                  value={checkoutForm.paymentMethod}
                  onChange={(event) =>
                    setCheckoutForm((current) => ({ ...current, paymentMethod: event.target.value }))
                  }
                >
                  <option value="ABA Mobile">ABA Mobile</option>
                  <option value="Wing">Wing</option>
                  <option value="Cash on Delivery">Cash on Delivery</option>
                </select>
              </label>
              <label>
                Delivery address
                <textarea
                  rows="3"
                  value={checkoutForm.shippingAddress}
                  onChange={(event) =>
                    setCheckoutForm((current) => ({ ...current, shippingAddress: event.target.value }))
                  }
                />
              </label>
              <button className="primary-btn full-btn" onClick={submitOrder}>
                Proceed to payment
              </button>
            </div>
          </aside>
        </section>}

        {user?.role === 'customer' && (
          <section className="customer-summary panel">
            <div className="panel-header">
              <div>
                <span className="eyebrow">My account</span>
                <h2>Your recent purchases</h2>
              </div>
            </div>

            {customerOrders.length === 0 ? (
              <p className="empty-message">No purchase history yet. Start shopping to build your order list.</p>
            ) : (
              <div className="order-list">
                {customerOrders.map((order) => (
                  <div key={order.id} className="order-card">
                    <div>
                      <strong>{order.id}</strong>
                      <small>{new Date(order.createdAt).toLocaleDateString()}</small>
                    </div>
                    <span>{order.status}</span>
                    <strong>{khr(order.total)}</strong>
                  </div>
                ))}
              </div>
            )}
          </section>
        )}

        {!isAdmin && <section className="support-grid" id="support">
          <div className="panel" id="reviews">
            <div className="panel-header">
              <div>
                <span className="eyebrow">Customer reviews</span>
                <h3>What shoppers say</h3>
              </div>
            </div>

            <div className="review-list">
              {feedback.map((item) => (
                <div key={item.id} className="review-item">
                  <div className="review-head">
                    <strong>{item.name}</strong>
                    <span>{'★'.repeat(Number(item.rating))}</span>
                  </div>
                  <p>{item.message}</p>
                </div>
              ))}
            </div>

            <form className="feedback-form" onSubmit={handleFeedbackSubmit}>
              <h4>Leave a rating</h4>
              <input type="text" name="name" placeholder="Your name" required />
              <select name="rating" defaultValue="5">
                <option value="5">5 stars</option>
                <option value="4">4 stars</option>
                <option value="3">3 stars</option>
                <option value="2">2 stars</option>
                <option value="1">1 star</option>
              </select>
              <textarea name="message" rows="4" placeholder="Your message" required />
              <button type="submit" className="primary-btn">Submit Rating</button>
            </form>
          </div>

          <div className="panel" id="about">
            <div className="panel-header">
              <div>
                <span className="eyebrow">Contact owner</span>
                <h3>Ask a question or chat</h3>
              </div>
            </div>

            <div className="message-list">
              {messages.map((message) => (
                <div key={message.id} className={`message-row ${message.sender}`}>
                  <strong>{message.name}</strong>
                  <p>{message.message}</p>
                </div>
              ))}
            </div>

            <form className="chat-form" onSubmit={handleChatSubmit}>
              <input type="text" name="message" placeholder="Type your message..." />
              <button type="submit" className="primary-btn">Send</button>
            </form>
          </div>
        </section>}
      </main>
    </div>
  );
}

export default App;
