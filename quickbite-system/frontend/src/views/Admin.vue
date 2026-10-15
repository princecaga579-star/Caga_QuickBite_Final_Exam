
<script setup>
import { ref, onMounted, computed } from 'vue'
import { api, money } from '../api'
import {
  LayoutDashboard,
  Package,
  ClipboardList,
  BarChart3,
  LogOut,
  Plus,
  RefreshCw,
  Save,
  Trash2,
  Boxes,
  TrendingUp,
  ShoppingBasket,
  X,
  Clock
} from 'lucide-vue-next'

const token = ref(localStorage.getItem('qb_admin_token') || '')
const user = ref(null)
const tab = ref('overview')
const data = ref(null)
const orders = ref([])
const report = ref(null)
const days = ref(7)
const error = ref('')
const loading = ref(false)
const showProduct = ref(false)
const showCategory = ref(false)
const editing = ref(null)

// Show/Hide Password
const showPassword = ref(false)

const product = ref({
  category_id: '',
  name: '',
  description: '',
  price: '',
  image_url: '',
  stock: 10,
  is_available: true,
  is_featured: false
})

const category = ref({
  name: '',
  description: ''
})

const loggedIn = computed(() => Boolean(token.value))

async function load() {
  if (!token.value) return

  loading.value = true
  error.value = ''

  try {
    const [meResponse, dashboardResponse] = await Promise.all([
      api.get('/admin/me'),
      api.get('/admin/dashboard')
    ])

    user.value = meResponse.data
    data.value = dashboardResponse.data
    orders.value = dashboardResponse.data.recent_orders || []
  } catch (e) {
    error.value =
      e.response?.data?.message || 'Unable to load admin data.'

    if (
      e.response?.status === 401 ||
      e.response?.status === 403
    ) {
      await logout(false)
    }
  } finally {
    loading.value = false
  }
}

async function login(event) {
  const formData = new FormData(event.target)
  error.value = ''

  try {
    const response = await api.post('/admin/login', {
      email: formData.get('email'),
      password: formData.get('password')
    })

    token.value = response.data.token

    localStorage.setItem(
      'qb_admin_token',
      response.data.token
    )

    showPassword.value = false

    await load()
  } catch (e) {
    error.value =
      e.response?.data?.message || 'Sign in failed.'
  }
}

async function logout(callApi = true) {
  if (callApi) {
    try {
      await api.post('/admin/logout')
    } catch {
      // Clear the local session even if the API request fails.
    }
  }

  token.value = ''
  user.value = null
  data.value = null
  orders.value = []
  report.value = null
  showPassword.value = false

  localStorage.removeItem('qb_admin_token')
}

function newProduct() {
  editing.value = null

  product.value = {
    category_id: data.value?.categories?.[0]?.id ?? '',
    name: '',
    description: '',
    price: '',
    image_url: '',
    stock: 10,
    is_available: true,
    is_featured: false
  }

  showProduct.value = true
}

function editProduct(item) {
  editing.value = item.id

  product.value = {
    category_id: item.category_id ?? item.category?.id ?? '',
    name: item.name ?? '',
    description: item.description ?? '',
    price: item.price ?? '',
    image_url: item.image_url ?? '',
    stock: item.stock ?? 0,
    is_available: Boolean(item.is_available),
    is_featured: Boolean(item.is_featured)
  }

  showProduct.value = true
}

async function saveProduct() {
  error.value = ''

  const payload = {
    ...product.value,
    category_id: product.value.category_id,
    price: Number(product.value.price),
    stock: Number(product.value.stock)
  }

  try {
    if (editing.value) {
      await api.put(
        `/admin/products/${editing.value}`,
        payload
      )
    } else {
      await api.post('/admin/products', payload)
    }

    showProduct.value = false
    await load()
  } catch (e) {
    error.value =
      Object.values(e.response?.data?.errors || {})
        .flat()
        .join(' ') ||
      e.response?.data?.message ||
      'Could not save product.'
  }
}

async function deleteProduct(item) {
  if (!confirm(`Delete ${item.name}?`)) return

  error.value = ''

  try {
    await api.delete(`/admin/products/${item.id}`)
    await load()
  } catch (e) {
    error.value =
      e.response?.data?.message || 'Delete failed.'
  }
}

async function saveCategory() {
  error.value = ''

  try {
    await api.post('/admin/categories', category.value)

    showCategory.value = false
    category.value = {
      name: '',
      description: ''
    }

    await load()
  } catch (e) {
    error.value =
      Object.values(e.response?.data?.errors || {})
        .flat()
        .join(' ') ||
      e.response?.data?.message ||
      'Could not create category.'
  }
}

async function status(order, newStatus) {
  error.value = ''

  try {
    await api.patch(
      `/admin/orders/${order.id}/status`,
      { status: newStatus }
    )

    await load()

    if (tab.value === 'orders') {
      await getOrders()
    }
  } catch (e) {
    error.value =
      e.response?.data?.message ||
      'Status update failed.'
  }
}

async function getOrders() {
  error.value = ''

  try {
    const response = await api.get('/admin/orders')

    orders.value = Array.isArray(response.data)
      ? response.data
      : response.data.data || []
  } catch (e) {
    error.value =
      e.response?.data?.message ||
      'Could not load orders.'
  }
}

async function getReport() {
  error.value = ''

  try {
    const response = await api.get(
      '/admin/reports/sales',
      { params: { days: days.value } }
    )

    report.value = response.data
  } catch (e) {
    error.value =
      e.response?.data?.message ||
      'Could not load sales report.'
  }
}

onMounted(async () => {
  if (token.value) {
    await load()
  }
})
</script>

<template>
  <section class="admin-page">

    <!-- Admin login -->
    <div v-if="!loggedIn" class="login-wrap">
      <div class="login-card">
        <div class="login-mark">
          <LayoutDashboard :size="28" />
        </div>

        <p class="eyebrow">QUICKBITE MANAGEMENT</p>
        <h1>Welcome <em>back.</em></h1>

        <p class="muted">
          Sign in to manage your menu, orders and sales.
        </p>

        <form @submit.prevent="login">
          <label>
            Email address

            <input
              name="email"
              type="email"
              required
              placeholder="admin@quickbite.test"
              autocomplete="username"
            />
          </label>

          <!-- Password with Show/Hide button -->
          <label>
            Password

            <div class="password-wrapper">
              <input
                name="password"
                :type="showPassword ? 'text' : 'password'"
                required
                placeholder="Your password"
                autocomplete="current-password"
              />

              <button
                type="button"
                class="toggle-password"
                @click="showPassword = !showPassword"
                :aria-label="
                  showPassword ? 'Hide password' : 'Show password'
                "
                :aria-pressed="showPassword"
              >
                {{ showPassword ? 'Hide' : 'Show' }}
              </button>
            </div>
          </label>

          <div v-if="error" class="alert">
            {{ error }}
          </div>

          <button class="btn primary wide" type="submit">
            Sign in to dashboard →
          </button>
        </form>

        <div class="demo-hint">
          DEMO ACCESS
          <b>admin@quickbite.test</b>
          <b>ChangeMe123!</b>
        </div>
      </div>
    </div>

    <!-- Admin dashboard -->
    <div v-else class="admin-layout">
      <aside class="admin-sidebar">
        <div class="admin-brand">
          <span class="brand-mark">
            <Package />
          </span>

          <b>
            quick<span class="orange">bite</span>
            <small>ADMIN PANEL</small>
          </b>
        </div>

        <p class="side-label">WORKSPACE</p>

        <button
          :class="{ active: tab === 'overview' }"
          @click="tab = 'overview'"
        >
          <LayoutDashboard />
          Overview
        </button>

        <button
          :class="{ active: tab === 'products' }"
          @click="tab = 'products'"
        >
          <Package />
          Products &amp; stock
        </button>

        <button
          :class="{ active: tab === 'orders' }"
          @click="tab = 'orders'; getOrders()"
        >
          <ClipboardList />
          Orders
        </button>

        <button
          :class="{ active: tab === 'reports' }"
          @click="tab = 'reports'; getReport()"
        >
          <BarChart3 />
          Sales reports
        </button>

        <div class="sidebar-bottom">
          <div class="admin-avatar">
            {{ user?.name?.slice(0, 1) || 'A' }}
          </div>

          <div class="admin-user">
            <b>{{ user?.name || 'Administrator' }}</b>
            <small>Store administrator</small>
          </div>

          <button
            class="logout-btn"
            title="Log out"
            @click="logout()"
          >
            <LogOut />
          </button>
        </div>
      </aside>

      <div class="admin-main">
        <header class="admin-top">
          <div>
            <p class="eyebrow">STORE MANAGEMENT</p>

            <h1>
              {{
                {
                  overview: 'Overview',
                  products: 'Products & inventory',
                  orders: 'Order management',
                  reports: 'Sales reports'
                }[tab]
              }}
            </h1>
          </div>

          <div class="admin-top-actions">
            <span class="live-dot">Store online</span>

            <button
              class="icon-btn"
              aria-label="Refresh dashboard"
              @click="load"
            >
              <RefreshCw :size="17" />
            </button>
          </div>
        </header>

        <div v-if="error" class="alert">
          {{ error }}
          <button @click="error = ''">Dismiss</button>
        </div>

        <div v-if="loading" class="loading-admin">
          Loading dashboard…
        </div>

        <template v-else-if="data">

          <!-- Overview -->
          <section v-if="tab === 'overview'">
            <div class="stat-grid">
              <div class="stat-card">
                <span class="stat-icon orange-bg">
                  <ClipboardList />
                </span>
                <small>Total orders</small>
                <strong>{{ data.stats.orders }}</strong>
                <span class="stat-foot">All-time orders</span>
              </div>

              <div class="stat-card">
                <span class="stat-icon yellow-bg">
                  <Clock />
                </span>
                <small>Pending orders</small>
                <strong>{{ data.stats.pending }}</strong>
                <span class="stat-foot">Need your attention</span>
              </div>

              <div class="stat-card">
                <span class="stat-icon green-bg">
                  <TrendingUp />
                </span>
                <small>Recorded revenue</small>
                <strong>{{ money(data.stats.revenue) }}</strong>
                <span class="stat-foot">
                  Confirmed and later stages
                </span>
              </div>

              <div class="stat-card">
                <span class="stat-icon purple-bg">
                  <Boxes />
                </span>
                <small>Low-stock products</small>
                <strong>{{ data.stats.low_stock }}</strong>
                <span class="stat-foot">5 units or less</span>
              </div>
            </div>

            <div class="admin-panel">
              <div class="panel-head">
                <div>
                  <h2>Recent orders</h2>
                  <p>Latest customer activity</p>
                </div>

                <button
                  class="text-btn"
                  @click="tab = 'orders'; getOrders()"
                >
                  View all <span>→</span>
                </button>
              </div>

              <div class="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Order</th>
                      <th>Customer</th>
                      <th>Date</th>
                      <th>Total</th>
                      <th>Status</th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr
                      v-for="order in data.recent_orders"
                      :key="order.id"
                    >
                      <td><b>{{ order.order_number }}</b></td>
                      <td>{{ order.customer_name }}</td>
                      <td>
                        {{ new Date(order.created_at).toLocaleDateString() }}
                      </td>
                      <td><b>{{ money(order.total) }}</b></td>
                      <td>
                        <span
                          class="status-pill"
                          :class="order.status"
                        >
                          {{ order.status }}
                        </span>
                      </td>
                    </tr>

                    <tr v-if="!data.recent_orders?.length">
                      <td colspan="5" class="table-empty">
                        No orders yet. They'll appear here.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="admin-panel">
              <div class="panel-head">
                <div>
                  <h2>Inventory at a glance</h2>
                  <p>Quick view of menu stock</p>
                </div>

                <button
                  class="text-btn"
                  @click="tab = 'products'"
                >
                  Manage menu →
                </button>
              </div>

              <div class="inventory-list">
                <div
                  v-for="item in data.products.slice(0, 5)"
                  :key="item.id"
                  class="inventory-row"
                >
                  <img :src="item.image_url" :alt="item.name" />

                  <div class="inventory-name">
                    <b>{{ item.name }}</b>
                    <small>{{ item.category?.name }}</small>
                  </div>

                  <span
                    class="stock-pill"
                    :class="{ low: item.stock <= 5 }"
                  >
                    {{ item.stock }} in stock
                  </span>

                  <strong>{{ money(item.price) }}</strong>
                </div>
              </div>
            </div>
          </section>

          <!-- Products and inventory -->
          <section v-if="tab === 'products'">
            <div class="admin-panel">
              <div class="panel-head">
                <div>
                  <h2>Your menu</h2>
                  <p>Manage products, prices and stock levels</p>
                </div>

                <div class="head-buttons">
                  <button
                    class="btn outline"
                    @click="showCategory = true"
                  >
                    <Plus :size="15" />
                    Category
                  </button>

                  <button
                    class="btn primary"
                    @click="newProduct"
                  >
                    <Plus :size="15" />
                    Add product
                  </button>
                </div>
              </div>

              <div class="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>Category</th>
                      <th>Price</th>
                      <th>Stock</th>
                      <th>Visibility</th>
                      <th>Actions</th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr
                      v-for="item in data.products"
                      :key="item.id"
                    >
                      <td>
                        <div class="table-product">
                          <img :src="item.image_url" :alt="item.name" />
                          <div>
                            <b>{{ item.name }}</b>
                            <small>
                              {{ item.is_featured ? '★ Featured' : '' }}
                            </small>
                          </div>
                        </div>
                      </td>

                      <td>{{ item.category?.name }}</td>
                      <td><b>{{ money(item.price) }}</b></td>

                      <td>
                        <span
                          class="stock-pill"
                          :class="{ low: item.stock <= 5 }"
                        >
                          {{ item.stock }}
                        </span>
                      </td>

                      <td>
                        <span
                          class="status-pill"
                          :class="item.is_available ? 'confirmed' : 'cancelled'"
                        >
                          {{ item.is_available ? 'Available' : 'Hidden' }}
                        </span>
                      </td>

                      <td>
                        <div class="action-buttons">
                          <button
                            class="mini-btn"
                            @click="editProduct(item)"
                          >
                            Edit
                          </button>

                          <button
                            class="mini-btn danger"
                            aria-label="Delete product"
                            @click="deleteProduct(item)"
                          >
                            <Trash2 :size="14" />
                          </button>
                        </div>
                      </td>
                    </tr>

                    <tr v-if="!data.products?.length">
                      <td colspan="6" class="table-empty">
                        No products found.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <!-- Orders -->
          <section v-if="tab === 'orders'">
            <div class="admin-panel">
              <div class="panel-head">
                <div>
                  <h2>Customer orders</h2>
                  <p>
                    Update order progress as you prepare each order
                  </p>
                </div>

                <button class="btn outline" @click="getOrders">
                  <RefreshCw :size="15" />
                  Refresh
                </button>
              </div>

              <div class="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Order</th>
                      <th>Customer</th>
                      <th>Items</th>
                      <th>Total</th>
                      <th>Payment</th>
                      <th>Status / action</th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr v-for="order in orders" :key="order.id">
                      <td>
                        <b>{{ order.order_number }}</b>
                        <small class="table-sub">
                          {{ new Date(order.created_at).toLocaleString() }}
                        </small>
                      </td>

                      <td>
                        {{ order.customer_name }}
                        <small class="table-sub">{{ order.phone }}</small>
                      </td>

                      <td>
                        {{
                          order.items?.reduce(
                            (total, item) => total + item.quantity,
                            0
                          ) || 0
                        }}
                        items
                      </td>

                      <td><b>{{ money(order.total) }}</b></td>

                      <td>
                        {{
                          order.payment_method === 'cod'
                            ? 'Cash on delivery'
                            : 'GCash'
                        }}
                        <small class="table-sub">
                          {{ order.payment_status }}
                        </small>
                      </td>

                      <td>
                        <select
                          class="status-select"
                          :value="order.status"
                          @change="status(order, $event.target.value)"
                        >
                          <option
                            v-for="orderStatus in [
                              'pending',
                              'confirmed',
                              'preparing',
                              'ready',
                              'completed',
                              'cancelled'
                            ]"
                            :key="orderStatus"
                            :value="orderStatus"
                          >
                            {{ orderStatus }}
                          </option>
                        </select>
                      </td>
                    </tr>

                    <tr v-if="!orders.length">
                      <td colspan="6" class="table-empty">
                        No orders to show.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <!-- Sales reports -->
          <section v-if="tab === 'reports'">
            <div class="report-toolbar">
              <div>
                <h2>Sales performance</h2>
                <p class="muted">
                  Revenue excludes cancelled and pending orders.
                </p>
              </div>

              <select v-model.number="days" @change="getReport">
                <option :value="7">Last 7 days</option>
                <option :value="30">Last 30 days</option>
                <option :value="90">Last 90 days</option>
              </select>
            </div>

            <div v-if="report" class="stat-grid report-stats">
              <div class="stat-card">
                <span class="stat-icon green-bg">
                  <TrendingUp />
                </span>
                <small>Recorded revenue</small>
                <strong>{{ money(report.total_revenue) }}</strong>
                <span class="stat-foot">Selected period</span>
              </div>

              <div class="stat-card">
                <span class="stat-icon orange-bg">
                  <ShoppingBasket />
                </span>
                <small>Orders placed</small>
                <strong>{{ report.total_orders }}</strong>
                <span class="stat-foot">
                  Including pending / cancelled
                </span>
              </div>
            </div>

            <div v-if="report" class="admin-panel">
              <div class="panel-head">
                <div>
                  <h2>Daily revenue</h2>
                  <p>Recorded revenue by order date</p>
                </div>
              </div>

              <div v-if="report.daily?.length" class="daily-bars">
                <div
                  v-for="day in report.daily"
                  :key="day.day"
                  class="bar-col"
                >
                  <b>{{ money(day.revenue) }}</b>

                  <div class="bar-track">
                    <div
                      class="bar-fill"
                      :style="{
                        height:
                          Math.max(
                            3,
                            Number(day.revenue) /
                              Math.max(
                                ...report.daily.map(
                                  item => Number(item.revenue)
                                ),
                                1
                              ) *
                              100
                          ) + '%'
                      }"
                    ></div>
                  </div>

                  <small>
                    {{
                      new Date(day.day + 'T00:00:00')
                        .toLocaleDateString(
                          undefined,
                          { month: 'short', day: 'numeric' }
                        )
                    }}
                  </small>
                </div>
              </div>

              <p v-else class="table-empty">
                No completed sales in this period yet.
              </p>

              <div class="panel-head best-head">
                <div>
                  <h2>Best sellers</h2>
                  <p>Ranked by units sold</p>
                </div>
              </div>

              <div
                v-if="report.best_sellers?.length"
                class="inventory-list"
              >
                <div
                  v-for="(item, index) in report.best_sellers"
                  :key="item.product_name"
                  class="inventory-row"
                >
                  <span class="rank-num">{{ index + 1 }}</span>

                  <div class="inventory-name">
                    <b>{{ item.product_name }}</b>
                    <small>{{ item.quantity_sold }} sold</small>
                  </div>

                  <strong>{{ money(item.revenue) }}</strong>
                </div>
              </div>

              <p v-else class="table-empty">
                No sales data yet.
              </p>
            </div>
          </section>

        </template>
      </div>
    </div>

    <!-- Add or edit product modal -->
    <div
      v-if="showProduct"
      class="overlay"
      @click.self="showProduct = false"
    >
      <section class="modal product-modal">
        <div class="drawer-head">
          <div>
            <p class="eyebrow">MENU EDITOR</p>
            <h2>
              {{ editing ? 'Edit product' : 'Add a product' }}
            </h2>
          </div>

          <button
            class="icon-btn"
            aria-label="Close product form"
            @click="showProduct = false"
          >
            <X />
          </button>
        </div>

        <form class="checkout-form" @submit.prevent="saveProduct">
          <label>
            Product name
            <input v-model="product.name" required />
          </label>

          <label>
            Category
            <select v-model="product.category_id" required>
              <option value="" disabled>Select category</option>

              <option
                v-for="itemCategory in data?.categories || []"
                :key="itemCategory.id"
                :value="itemCategory.id"
              >
                {{ itemCategory.name }}
              </option>
            </select>
          </label>

          <label>
            Description
            <textarea v-model="product.description"></textarea>
          </label>

          <div class="form-row">
            <label>
              Price (PHP)
              <input
                v-model.number="product.price"
                type="number"
                min="0.01"
                step="0.01"
                required
              />
            </label>

            <label>
              Stock quantity
              <input
                v-model.number="product.stock"
                type="number"
                min="0"
                required
              />
            </label>
          </div>

          <label>
            Image URL
            <input
              v-model="product.image_url"
              type="url"
              placeholder="https://example.com/food.jpg"
            />
          </label>

          <label class="check-label">
            <input v-model="product.is_available" type="checkbox" />
            Available to order
          </label>

          <label class="check-label">
            <input v-model="product.is_featured" type="checkbox" />
            Mark as featured
          </label>

          <button class="btn primary wide" type="submit">
            <Save :size="16" />
            Save product
          </button>
        </form>
      </section>
    </div>

    <!-- Create category modal -->
    <div
      v-if="showCategory"
      class="overlay"
      @click.self="showCategory = false"
    >
      <section class="modal">
        <div class="drawer-head">
          <h2>New category</h2>

          <button
            class="icon-btn"
            aria-label="Close category form"
            @click="showCategory = false"
          >
            <X />
          </button>
        </div>

        <form class="checkout-form" @submit.prevent="saveCategory">
          <label>
            Category name
            <input v-model="category.name" required />
          </label>

          <label>
            Description
            <textarea v-model="category.description"></textarea>
          </label>

          <button class="btn primary wide" type="submit">
            Create category
          </button>
        </form>
      </section>
    </div>

  </section>
</template>
