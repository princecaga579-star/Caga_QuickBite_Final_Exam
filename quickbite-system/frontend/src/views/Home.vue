
<script setup>
import { ref, computed, onMounted } from 'vue'
import { api, money } from '../api'
import {
  Search,
  ShoppingCart,
  Plus,
  Minus,
  X,
  ArrowRight,
  UtensilsCrossed,
  Clock3,
  Flame,
  Truck,
  RefreshCw
} from 'lucide-vue-next'

const products = ref([])
const categories = ref([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const selectedCategory = ref('all')

const cart = ref([])
const cartOpen = ref(false)
const checkout = ref(false)
const submitting = ref(false)
const orderComplete = ref(false)
const orderNumber = ref('')

const customer = ref({
  customer_name: '',
  phone: '',
  address: '',
  payment_method: 'cod'
})

const cartCount = computed(() =>
  cart.value.reduce((total, item) => total + item.quantity, 0)
)

const cartTotal = computed(() =>
  cart.value.reduce(
    (total, item) => total + Number(item.price) * item.quantity,
    0
  )
)

const visibleProducts = computed(() => {
  const term = search.value.trim().toLowerCase()

  return products.value.filter(product => {
    const available =
      product.is_available !== false &&
      Number(product.stock ?? 1) > 0

    const categoryMatches =
      selectedCategory.value === 'all' ||
      String(product.category_id ?? product.category?.id) ===
        String(selectedCategory.value)

    const searchMatches =
      !term ||
      product.name?.toLowerCase().includes(term) ||
      product.description?.toLowerCase().includes(term) ||
      product.category?.name?.toLowerCase().includes(term)

    return available && categoryMatches && searchMatches
  })
})

const featuredProducts = computed(() =>
  visibleProducts.value.filter(product => product.is_featured)
)

function normalizeList(responseData) {
  if (Array.isArray(responseData)) return responseData
  if (Array.isArray(responseData?.data)) return responseData.data
  if (Array.isArray(responseData?.data?.data)) {
    return responseData.data.data
  }

  return []
}

function saveCart() {
  localStorage.setItem('qb_cart', JSON.stringify(cart.value))
}

async function loadCatalog() {
  loading.value = true
  error.value = ''

  try {
    const [productResponse, categoryResponse] = await Promise.all([
      api.get('/products'),
      api.get('/categories')
    ])

    products.value = normalizeList(productResponse.data)
    categories.value = normalizeList(categoryResponse.data)

    const savedCart = JSON.parse(
      localStorage.getItem('qb_cart') || '[]'
    )

    if (Array.isArray(savedCart)) {
      cart.value = savedCart
        .filter(item => item && item.id)
        .map(item => ({
          ...item,
          price: Number(item.price) || 0,
          quantity: Math.max(1, Number(item.quantity) || 1)
        }))
    }
  } catch (e) {
    error.value =
      e.response?.data?.message ||
      'Unable to load the food menu. Please check your connection and try again.'
  } finally {
    loading.value = false
  }
}

function addToCart(product) {
  const currentStock = Number(product.stock ?? 999)

  const existing = cart.value.find(
    item => item.id === product.id
  )

  if (existing) {
    if (existing.quantity >= currentStock) {
      error.value = 'You have reached the available stock for this item.'
      return
    }

    existing.quantity += 1
  } else {
    cart.value.push({
      id: product.id,
      name: product.name,
      price: Number(product.price),
      image_url: product.image_url || '',
      stock: currentStock,
      quantity: 1
    })
  }

  error.value = ''
  saveCart()
  cartOpen.value = true
  checkout.value = false
  orderComplete.value = false
}

function changeQuantity(item, amount) {
  const nextQuantity = item.quantity + amount

  if (nextQuantity <= 0) {
    removeFromCart(item.id)
    return
  }

  if (nextQuantity > item.stock) {
    error.value = 'Not enough stock available.'
    return
  }

  item.quantity = nextQuantity
  error.value = ''
  saveCart()
}

function removeFromCart(id) {
  cart.value = cart.value.filter(item => item.id !== id)
  saveCart()
}

function openCheckout() {
  if (!cart.value.length) return

  error.value = ''
  checkout.value = true
}

async function placeOrder() {
  if (!cart.value.length) {
    error.value = 'Your cart is empty.'
    return
  }

  submitting.value = true
  error.value = ''

  try {
    const payload = {
      customer_name: customer.value.customer_name,
      phone: customer.value.phone,
      address: customer.value.address,
      payment_method: customer.value.payment_method,
      items: cart.value.map(item => ({
        product_id: item.id,
        quantity: item.quantity
      }))
    }

    const response = await api.post('/orders', payload)
    const result = response.data?.order || response.data?.data || response.data

    orderNumber.value =
      result?.order_number ||
      result?.order?.order_number ||
      response.data?.order_number ||
      ''

    cart.value = []
    saveCart()

    orderComplete.value = true
    checkout.value = false
  } catch (e) {
    const validationErrors = Object.values(
      e.response?.data?.errors || {}
    ).flat()

    error.value =
      validationErrors.join(' ') ||
      e.response?.data?.message ||
      'We could not place your order. Please try again.'
  } finally {
    submitting.value = false
  }
}

function closeCart() {
  cartOpen.value = false
  checkout.value = false
  orderComplete.value = false
  error.value = ''
}

onMounted(loadCatalog)
</script>

<template>
  <main class="qb-store">
    <header class="qb-header">
      <a href="/" class="qb-logo" aria-label="QuickBite home">
        <span class="qb-logo-icon">
          <UtensilsCrossed :size="24" />
        </span>

        <span>quick<span class="qb-orange">bite</span></span>
      </a>

      <nav class="qb-nav">
        <a href="#home">Home</a>
        <a href="#menu">Our menu</a>
        <a href="#about">Why QuickBite</a>
      </nav>

      <button
        class="qb-cart-button"
        @click="cartOpen = true"
      >
        <ShoppingCart :size="19" />
        <span>Cart</span>
        <span class="qb-cart-count">{{ cartCount }}</span>
      </button>
    </header>

    <section id="home" class="qb-hero">
      <div class="qb-hero-copy">
        <span class="qb-kicker">
          <Flame :size="16" />
          FRESHLY MADE, JUST FOR YOU
        </span>

        <h1>
          Good food.<br />
          <span>Good mood.</span>
        </h1>

        <p>
          Your cravings, covered. Discover delicious meals,
          snacks, and drinks at prices you'll love.
        </p>

        <div class="qb-hero-actions">
          <a href="#menu" class="qb-primary-button">
            Explore menu
            <ArrowRight :size="18" />
          </a>

          <div class="qb-delivery-note">
            <Truck :size="20" />
            <span>
              <b>Easy ordering</b>
              <small>Simple online checkout</small>
            </span>
          </div>
        </div>

        <div class="qb-hero-stats">
          <div>
            <b>Fresh</b>
            <small>Menu favourites</small>
          </div>
          <div>
            <b>Easy</b>
            <small>Online ordering</small>
          </div>
          <div>
            <b>Quick</b>
            <small>Order updates</small>
          </div>
        </div>
      </div>

      <div class="qb-hero-visual">
        <div class="qb-hero-circle"></div>

        <div class="qb-hero-food">
          <img
            src="https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=1000&q=85"
            alt="Juicy burger with fresh toppings"
          />
        </div>

        <div class="qb-floating-card qb-floating-top">
          <span class="qb-floating-icon">
            <Flame :size="20" />
          </span>
          <span>
            <b>Your favourites</b>
            <small>All in one place</small>
          </span>
        </div>

        <div class="qb-floating-card qb-floating-bottom">
          <Clock3 :size="22" />
          <span>
            <b>Order with ease</b>
            <small>From menu to checkout</small>
          </span>
        </div>
      </div>
    </section>

    <section class="qb-benefits" id="about">
      <div>
        <span class="qb-benefit-icon">
          <UtensilsCrossed :size="21" />
        </span>
        <span>
          <b>A menu for every craving</b>
          <small>Discover meals and snacks</small>
        </span>
      </div>

      <div>
        <span class="qb-benefit-icon">
          <Truck :size="21" />
        </span>
        <span>
          <b>Convenient ordering</b>
          <small>Order in just a few steps</small>
        </span>
      </div>

      <div>
        <span class="qb-benefit-icon">
          <ShoppingCart :size="21" />
        </span>
        <span>
          <b>Easy checkout</b>
          <small>Review your order before placing it</small>
        </span>
      </div>
    </section>

    <section id="menu" class="qb-menu-section">
      <div class="qb-menu-heading">
        <div>
          <span class="qb-kicker">THE QUICKBITE MENU</span>
          <h2>Find your next <span>favourite.</span></h2>
          <p>Good eats are just a few clicks away.</p>
        </div>

        <label class="qb-search">
          <Search :size="19" />
          <input
            v-model="search"
            type="search"
            placeholder="Search food..."
          />
          <button
            v-if="search"
            type="button"
            aria-label="Clear search"
            @click="search = ''"
          >
            <X :size="16" />
          </button>
        </label>
      </div>

      <div class="qb-categories">
        <button
          :class="{ active: selectedCategory === 'all' }"
          @click="selectedCategory = 'all'"
        >
          All food
        </button>

        <button
          v-for="category in categories"
          :key="category.id"
          :class="{
            active: String(selectedCategory) === String(category.id)
          }"
          @click="selectedCategory = String(category.id)"
        >
          {{ category.name }}
        </button>
      </div>

      <div v-if="error && !cartOpen" class="qb-error">
        {{ error }}
        <button @click="loadCatalog">
          <RefreshCw :size="15" /> Try again
        </button>
      </div>

      <div v-if="loading" class="qb-loading">
        <span class="qb-spinner"></span>
        <p>Preparing your menu...</p>
      </div>

      <template v-else>
        <div
          v-if="featuredProducts.length && selectedCategory === 'all' && !search"
          class="qb-featured"
        >
          <div class="qb-featured-heading">
            <h3><Flame :size="19" /> Popular picks</h3>
            <span>Customer favourites</span>
          </div>

          <div class="qb-featured-grid">
            <article
              v-for="product in featuredProducts.slice(0, 3)"
              :key="product.id"
              class="qb-featured-card"
            >
              <img
                v-if="product.image_url"
                :src="product.image_url"
                :alt="product.name"
                loading="lazy"
              />

              <div v-else class="qb-food-placeholder">
                <UtensilsCrossed :size="34" />
              </div>

              <div class="qb-featured-info">
                <span>FEATURED</span>
                <h4>{{ product.name }}</h4>
                <div>
                  <b>{{ money(product.price) }}</b>

                  <button
                    aria-label="Add featured food to cart"
                    @click="addToCart(product)"
                  >
                    <Plus :size="19" />
                  </button>
                </div>
              </div>
            </article>
          </div>
        </div>

        <div class="qb-all-heading">
          <h3>
            {{
              search
                ? 'Search results'
                : selectedCategory === 'all'
                  ? 'Explore our menu'
                  : categories.find(
                      category =>
                        String(category.id) === String(selectedCategory)
                    )?.name || 'Explore our menu'
            }}
          </h3>

          <span>
            {{ visibleProducts.length }}
            {{ visibleProducts.length === 1 ? 'item' : 'items' }}
          </span>
        </div>

        <div v-if="visibleProducts.length" class="qb-product-grid">
          <article
            v-for="product in visibleProducts"
            :key="product.id"
            class="qb-product-card"
          >
            <div class="qb-product-image">
              <img
                v-if="product.image_url"
                :src="product.image_url"
                :alt="product.name"
                loading="lazy"
              />

              <div v-else class="qb-food-placeholder">
                <UtensilsCrossed :size="36" />
              </div>

              <span
                v-if="product.is_featured"
                class="qb-product-badge"
              >
                <Flame :size="12" />
                Popular
              </span>

              <span
                v-if="Number(product.stock) <= 5"
                class="qb-stock-badge"
              >
                Limited stock
              </span>
            </div>

            <div class="qb-product-info">
              <span class="qb-product-category">
                {{ product.category?.name || 'QuickBite menu' }}
              </span>

              <h4>{{ product.name }}</h4>

              <p>
                {{ product.description || 'A delicious QuickBite favourite.' }}
              </p>

              <div class="qb-product-bottom">
                <strong>{{ money(product.price) }}</strong>

                <button
                  class="qb-add-button"
                  :aria-label="'Add ' + product.name + ' to cart'"
                  @click="addToCart(product)"
                >
                  <Plus :size="19" />
                  Add
                </button>
              </div>
            </div>
          </article>
        </div>

        <div v-else class="qb-empty-menu">
          <span><Search :size="28" /></span>
          <h3>No food found</h3>
          <p>Try another search or choose a different category.</p>
          <button
            class="qb-primary-button"
            @click="search = ''; selectedCategory = 'all'"
          >
            View all food
          </button>
        </div>
      </template>
    </section>

    <footer class="qb-footer">
      <a href="#home" class="qb-logo">
        <span class="qb-logo-icon">
          <UtensilsCrossed :size="21" />
        </span>
        <span>quick<span class="qb-orange">bite</span></span>
      </a>

      <p>Good food, made easy.</p>
      <small>© {{ new Date().getFullYear() }} QuickBite. All rights reserved.</small>
    </footer>

    <!-- Cart and checkout drawer -->
    <div
      v-if="cartOpen"
      class="qb-overlay"
      @click.self="closeCart"
    >
      <aside class="qb-drawer">
        <header class="qb-drawer-header">
          <div>
            <span class="qb-kicker">YOUR ORDER</span>
            <h2>
              {{
                orderComplete
                  ? 'Order placed!'
                  : checkout
                    ? 'Checkout'
                    : 'Your cart'
              }}
            </h2>
          </div>

          <button
            class="qb-close-button"
            aria-label="Close cart"
            @click="closeCart"
          >
            <X :size="21" />
          </button>
        </header>

        <div v-if="error" class="qb-drawer-error">
          {{ error }}
        </div>

        <div v-if="orderComplete" class="qb-success">
          <div class="qb-success-icon">✓</div>

          <h3>Thank you for your order!</h3>

          <p>
            Your order has been submitted successfully.
          </p>

          <div v-if="orderNumber" class="qb-order-number">
            <small>ORDER NUMBER</small>
            <b>{{ orderNumber }}</b>
          </div>

          <p class="qb-small-note">
            Keep your order number so you can check its status.
          </p>

          <button
            class="qb-primary-button qb-full-button"
            @click="closeCart"
          >
            Continue shopping
            <ArrowRight :size="17" />
          </button>
        </div>

        <template v-else-if="checkout">
          <button
            class="qb-back-button"
            @click="checkout = false; error = ''"
          >
            ← Back to cart
          </button>

          <form
            class="qb-checkout-form"
            @submit.prevent="placeOrder"
          >
            <h3>Delivery details</h3>

            <label>
              Full name
              <input
                v-model.trim="customer.customer_name"
                required
                maxlength="120"
                autocomplete="name"
                placeholder="Enter your name"
              />
            </label>

            <label>
              Phone number
              <input
                v-model.trim="customer.phone"
                required
                maxlength="30"
                autocomplete="tel"
                placeholder="09XX XXX XXXX"
              />
            </label>

            <label>
              Delivery address
              <textarea
                v-model.trim="customer.address"
                required
                maxlength="1000"
                autocomplete="street-address"
                placeholder="House number, street, barangay..."
                rows="3"
              ></textarea>
            </label>

            <h3>Payment method</h3>

            <label class="qb-payment-option">
              <input
                v-model="customer.payment_method"
                type="radio"
                value="cod"
              />
              <span>
                <b>Cash on delivery</b>
                <small>Pay when your order arrives.</small>
              </span>
            </label>

            <label class="qb-payment-option">
              <input
                v-model="customer.payment_method"
                type="radio"
                value="gcash"
              />
              <span>
                <b>GCash</b>
                <small>
                  Follow the store's payment instructions after placing your order.
                </small>
              </span>
            </label>

            <div class="qb-checkout-total">
              <span>Total</span>
              <strong>{{ money(cartTotal) }}</strong>
            </div>

            <button
              class="qb-primary-button qb-full-button"
              type="submit"
              :disabled="submitting || !cart.length"
            >
              {{
                submitting
                  ? 'Placing order...'
                  : 'Place my order'
              }}
              <ArrowRight v-if="!submitting" :size="17" />
            </button>
          </form>
        </template>

        <template v-else>
          <div v-if="!cart.length" class="qb-empty-cart">
            <ShoppingCart :size="42" />
            <h3>Your cart is empty</h3>
            <p>Explore our menu and add something delicious.</p>

            <button
              class="qb-primary-button"
              @click="closeCart"
            >
              Browse menu
            </button>
          </div>

          <template v-else>
            <div class="qb-cart-list">
              <article
                v-for="item in cart"
                :key="item.id"
                class="qb-cart-item"
              >
                <img
                  v-if="item.image_url"
                  :src="item.image_url"
                  :alt="item.name"
                />

                <div v-else class="qb-cart-placeholder">
                  <UtensilsCrossed :size="23" />
                </div>

                <div class="qb-cart-item-info">
                  <h4>{{ item.name }}</h4>
                  <b>{{ money(item.price) }}</b>

                  <div class="qb-quantity">
                    <button
                      type="button"
                      aria-label="Decrease quantity"
                      @click="changeQuantity(item, -1)"
                    >
                      <Minus :size="14" />
                    </button>

                    <span>{{ item.quantity }}</span>

                    <button
                      type="button"
                      aria-label="Increase quantity"
                      @click="changeQuantity(item, 1)"
                    >
                      <Plus :size="14" />
                    </button>
                  </div>
                </div>

                <div class="qb-cart-item-right">
                  <b>{{ money(item.price * item.quantity) }}</b>

                  <button
                    class="qb-remove-button"
                    aria-label="Remove item"
                    @click="removeFromCart(item.id)"
                  >
                    Remove
                  </button>
                </div>
              </article>
            </div>

            <div class="qb-cart-summary">
              <div class="qb-summary-row">
                <span>Subtotal</span>
                <b>{{ money(cartTotal) }}</b>
              </div>

              <p>
                Delivery charges, if applicable, are confirmed by the store.
              </p>

              <button
                class="qb-primary-button qb-full-button"
                @click="openCheckout"
              >
                Continue to checkout
                <ArrowRight :size="17" />
              </button>
            </div>
          </template>
        </template>
      </aside>
    </div>
  </main>
</template>

<style scoped>
.qb-store {
  --orange: #f26a21;
  --orange-dark: #d94e0d;
  --ink: #252522;
  --muted: #77766e;
  --cream: #fffaf3;
  --line: #eee8df;
  color: var(--ink);
  background: #fff;
  min-height: 100vh;
  font-family: Inter, "Segoe UI", Arial, sans-serif;
}

.qb-store * {
  box-sizing: border-box;
}

.qb-store button,
.qb-store input,
.qb-store textarea {
  font: inherit;
}

.qb-store button {
  cursor: pointer;
}

.qb-orange,
.qb-kicker {
  color: var(--orange);
}

.qb-header {
  max-width: 1240px;
  min-height: 82px;
  padding: 16px 28px;
  margin: auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}

.qb-logo {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 25px;
  font-weight: 850;
  color: var(--ink);
  text-decoration: none;
  letter-spacing: -1.1px;
}

.qb-logo-icon {
  display: grid;
  place-items: center;
  width: 43px;
  height: 43px;
  border-radius: 14px;
  color: #fff;
  background: var(--orange);
}

.qb-nav {
  display: flex;
  align-items: center;
  gap: 34px;
}

.qb-nav a {
  color: #696860;
  text-decoration: none;
  font-size: 14px;
  font-weight: 650;
}

.qb-nav a:hover {
  color: var(--orange);
}

.qb-cart-button {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  border: 1px solid var(--line);
  background: #fff;
  border-radius: 13px;
  padding: 11px 15px;
  font-weight: 700;
  color: var(--ink);
}

.qb-cart-count {
  display: grid;
  place-items: center;
  min-width: 23px;
  height: 23px;
  padding: 0 5px;
  color: #fff;
  background: var(--orange);
  border-radius: 8px;
  font-size: 12px;
}

.qb-hero {
  max-width: 1240px;
  min-height: 490px;
  padding: 46px 48px 50px;
  margin: 0 auto;
  border-radius: 27px;
  background: var(--cream);
  display: grid;
  grid-template-columns: 1fr 0.9fr;
  align-items: center;
  overflow: hidden;
}

.qb-hero-copy {
  position: relative;
  z-index: 2;
}

.qb-kicker {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 11px;
  font-weight: 850;
  letter-spacing: 1.8px;
}

.qb-hero h1 {
  font-size: clamp(48px, 5.5vw, 73px);
  line-height: 1.03;
  letter-spacing: -4px;
  margin: 24px 0 18px;
  font-weight: 900;
}

.qb-hero h1 span,
.qb-menu-heading h2 span {
  color: var(--orange);
}

.qb-hero-copy > p {
  max-width: 445px;
  margin: 0;
  color: #77766e;
  line-height: 1.8;
  font-size: 15px;
}

.qb-hero-actions {
  display: flex;
  align-items: center;
  gap: 25px;
  flex-wrap: wrap;
  margin-top: 30px;
}

.qb-primary-button {
  min-height: 48px;
  padding: 13px 19px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  border: 0;
  border-radius: 12px;
  color: #fff;
  background: var(--orange);
  text-decoration: none;
  font-size: 14px;
  font-weight: 800;
  transition: background 0.2s, transform 0.2s;
}

.qb-primary-button:hover {
  background: var(--orange-dark);
  transform: translateY(-1px);
}

.qb-primary-button:disabled {
  cursor: wait;
  opacity: 0.65;
}

.qb-delivery-note {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--orange);
}

.qb-delivery-note span,
.qb-floating-card span {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.qb-delivery-note b,
.qb-floating-card b {
  font-size: 12px;
  color: var(--ink);
}

.qb-delivery-note small,
.qb-floating-card small {
  font-size: 10px;
  color: var(--muted);
}

.qb-hero-stats {
  display: flex;
  gap: 35px;
  border-top: 1px solid #e9dfd1;
  padding-top: 22px;
  margin-top: 40px;
}

.qb-hero-stats div {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.qb-hero-stats b {
  font-size: 16px;
}

.qb-hero-stats small {
  color: var(--muted);
  font-size: 11px;
}

.qb-hero-visual {
  position: relative;
  min-height: 370px;
  display: grid;
  place-items: center;
}

.qb-hero-circle {
  position: absolute;
  width: min(350px, 90%);
  aspect-ratio: 1;
  background: #f7d7b8;
  border-radius: 50%;
}

.qb-hero-food {
  position: relative;
  z-index: 1;
  width: min(365px, 95%);
  aspect-ratio: 1;
  padding: 10px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 25px 65px #a45d2b22;
  transform: rotate(-5deg);
}

.qb-hero-food img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
  border-radius: 50%;
}

.qb-floating-card {
  position: absolute;
  z-index: 2;
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 14px 16px;
  border-radius: 15px;
  background: #fff;
  box-shadow: 0 12px 35px #3a241512;
}

.qb-floating-top {
  top: 45px;
  right: -5px;
}

.qb-floating-bottom {
  left: -15px;
  bottom: 36px;
  color: var(--orange);
}

.qb-floating-icon {
  display: grid;
  place-items: center;
  color: var(--orange);
  background: #fff0e5;
  width: 37px;
  height: 37px;
  border-radius: 11px;
}

.qb-benefits {
  max-width: 1150px;
  margin: 24px auto 0;
  padding: 24px;
  border: 1px solid var(--line);
  border-radius: 19px;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 25px;
}

.qb-benefits > div {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.qb-benefit-icon {
  width: 43px;
  height: 43px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  border-radius: 13px;
  background: #fff0e5;
  color: var(--orange);
}

.qb-benefits > div > span:last-child {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.qb-benefits b {
  font-size: 12px;
}

.qb-benefits small {
  font-size: 10px;
  color: var(--muted);
}

.qb-menu-section {
  max-width: 1240px;
  padding: 80px 28px 90px;
  margin: auto;
}

.qb-menu-heading {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 25px;
  margin-bottom: 28px;
}

.qb-menu-heading h2 {
  margin: 13px 0 8px;
  font-size: clamp(29px, 4vw, 43px);
  line-height: 1.15;
  letter-spacing: -1.7px;
}

.qb-menu-heading p {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.qb-search {
  width: min(310px, 100%);
  min-height: 46px;
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 0 13px;
  border: 1px solid var(--line);
  border-radius: 12px;
  color: var(--muted);
}

.qb-search input {
  flex: 1;
  width: 100%;
  outline: 0;
  border: 0;
  background: transparent;
  color: var(--ink);
  font-size: 13px;
}

.qb-search button {
  border: 0;
  background: transparent;
  padding: 3px;
  color: var(--muted);
}

.qb-categories {
  display: flex;
  gap: 9px;
  flex-wrap: wrap;
  margin-bottom: 37px;
}

.qb-categories button {
  padding: 10px 16px;
  border-radius: 10px;
  border: 1px solid var(--line);
  background: #fff;
  color: #77766e;
  font-size: 12px;
  font-weight: 750;
}

.qb-categories button.active {
  background: var(--orange);
  border-color: var(--orange);
  color: #fff;
}

.qb-featured {
  margin-bottom: 45px;
}

.qb-featured-heading,
.qb-all-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 19px;
}

.qb-featured-heading h3,
.qb-all-heading h3 {
  margin: 0;
  font-size: 19px;
  letter-spacing: -0.4px;
}

.qb-featured-heading h3 {
  display: flex;
  align-items: center;
  gap: 8px;
}

.qb-featured-heading h3 svg {
  color: var(--orange);
}

.qb-featured-heading > span,
.qb-all-heading > span {
  font-size: 12px;
  color: var(--muted);
}

.qb-featured-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 18px;
}

.qb-featured-card {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 16px;
  background: #fff;
}

.qb-featured-card > img,
.qb-featured-card > .qb-food-placeholder {
  width: 100%;
  height: 185px;
  object-fit: cover;
}

.qb-featured-info {
  padding: 15px;
}

.qb-featured-info > span {
  color: var(--orange);
  font-size: 9px;
  font-weight: 850;
  letter-spacing: 1.2px;
}

.qb-featured-info h4 {
  margin: 8px 0 14px;
  font-size: 15px;
}

.qb-featured-info > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.qb-featured-info > div > b {
  font-size: 17px;
}

.qb-featured-info button {
  width: 37px;
  height: 37px;
  border: 0;
  border-radius: 11px;
  color: #fff;
  background: var(--orange);
}

.qb-all-heading {
  margin-top: 5px;
}

.qb-product-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
}

.qb-product-card {
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 17px;
  background: #fff;
  transition: transform 0.2s, box-shadow 0.2s;
}

.qb-product-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 35px #3424140b;
}

.qb-product-image {
  position: relative;
  height: 190px;
  overflow: hidden;
  background: #f9f3eb;
}

.qb-product-image > img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.25s;
}

.qb-product-card:hover .qb-product-image > img {
  transform: scale(1.04);
}

.qb-food-placeholder {
  display: grid;
  place-items: center;
  background: #f8ede1;
  color: #cc8b5e;
}

.qb-product-badge,
.qb-stock-badge {
  position: absolute;
  top: 11px;
  display: flex;
  align-items: center;
  gap: 4px;
  border-radius: 7px;
  padding: 6px 8px;
  font-size: 9px;
  font-weight: 800;
}

.qb-product-badge {
  left: 10px;
  color: #b84a0b;
  background: #fff2e7;
}

.qb-stock-badge {
  right: 10px;
  background: #fff;
  color: #9e5419;
}

.qb-product-info {
  padding: 15px;
}

.qb-product-category {
  font-size: 10px;
  font-weight: 750;
  color: var(--orange);
  text-transform: uppercase;
  letter-spacing: 0.8px;
}

.qb-product-info h4 {
  margin: 7px 0;
  font-size: 16px;
  line-height: 1.35;
}

.qb-product-info > p {
  min-height: 35px;
  margin: 0 0 18px;
  font-size: 11px;
  line-height: 1.6;
  color: var(--muted);
}

.qb-product-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 7px;
}

.qb-product-bottom > strong {
  font-size: 16px;
}

.qb-add-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  min-height: 37px;
  padding: 8px 10px;
  border: 0;
  border-radius: 10px;
  background: #fff0e5;
  color: #c84c0c;
  font-weight: 800;
  font-size: 11px;
}

.qb-add-button:hover {
  background: var(--orange);
  color: #fff;
}

.qb-empty-menu,
.qb-loading {
  min-height: 280px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: var(--muted);
}

.qb-empty-menu > span {
  display: grid;
  place-items: center;
  width: 65px;
  height: 65px;
  border-radius: 20px;
  color: var(--orange);
  background: #fff0e5;
}

.qb-empty-menu h3 {
  color: var(--ink);
  margin: 17px 0 5px;
}

.qb-empty-menu p,
.qb-loading p {
  font-size: 13px;
}

.qb-empty-menu button {
  margin-top: 10px;
}

.qb-spinner {
  width: 30px;
  height: 30px;
  border: 3px solid var(--line);
  border-top-color: var(--orange);
  border-radius: 50%;
  animation: qb-spin 0.8s linear infinite;
}

@keyframes qb-spin {
  to {
    transform: rotate(360deg);
  }
}

.qb-error,
.qb-drawer-error {
  border-radius: 11px;
  padding: 12px 14px;
  background: #fff0ef;
  color: #a92c24;
  font-size: 12px;
  line-height: 1.6;
}

.qb-error {
  margin: 20px 0;
}

.qb-error button {
  display: inline-flex;
  gap: 5px;
  align-items: center;
  background: transparent;
  color: inherit;
  border: 0;
  font-weight: 800;
  margin-left: 8px;
}

.qb-footer {
  padding: 28px;
  border-top: 1px solid var(--line);
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 17px;
}

.qb-footer p {
  color: var(--muted);
  font-size: 12px;
}

.qb-footer > small {
  color: var(--muted);
  font-size: 10px;
}

.qb-overlay {
  position: fixed;
  z-index: 1000;
  inset: 0;
  display: flex;
  justify-content: flex-end;
  background: #21170db3;
  animation: qb-fade-in 0.2s ease;
}

@keyframes qb-fade-in {
  from { opacity: 0; }
  to { opacity: 1; }
}

.qb-drawer {
  display: flex;
  flex-direction: column;
  width: min(480px, 100%);
  height: 100%;
  overflow-y: auto;
  padding: 25px;
  background: #fff;
  box-shadow: -15px 0 50px #00000012;
}

.qb-drawer-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 15px;
  padding-bottom: 21px;
  border-bottom: 1px solid var(--line);
}

.qb-drawer-header h2 {
  margin: 7px 0 0;
  font-size: 27px;
  letter-spacing: -1px;
}

.qb-close-button {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 1px solid var(--line);
  border-radius: 11px;
  color: var(--ink);
  background: #fff;
}

.qb-cart-list {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.qb-cart-item {
  display: grid;
  grid-template-columns: 75px minmax(0, 1fr) auto;
  gap: 13px;
  align-items: start;
  padding: 19px 0;
  border-bottom: 1px solid var(--line);
}

.qb-cart-item > img,
.qb-cart-placeholder {
  width: 75px;
  height: 75px;
  border-radius: 12px;
  object-fit: cover;
}

.qb-cart-placeholder {
  display: grid;
  place-items: center;
  color: var(--orange);
  background: #fff0e5;
}

.qb-cart-item-info h4 {
  margin: 0 0 7px;
  font-size: 13px;
}

.qb-cart-item-info > b,
.qb-cart-item-right > b {
  font-size: 12px;
}

.qb-quantity {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  border: 1px solid var(--line);
  border-radius: 9px;
  padding: 4px;
  margin-top: 12px;
}

.qb-quantity button {
  display: grid;
  place-items: center;
  border: 0;
  border-radius: 5px;
  background: #fff5ec;
  color: var(--orange);
  width: 25px;
  height: 25px;
}

.qb-quantity span {
  font-size: 12px;
  font-weight: 800;
}

.qb-cart-item-right {
  display: flex;
  flex-direction: column;
  align-items: end;
  gap: 14px;
}

.qb-remove-button {
  background: transparent;
  border: 0;
  color: #a24a35;
  font-size: 10px;
}

.qb-cart-summary {
  margin-top: auto;
  padding-top: 20px;
}

.qb-summary-row,
.qb-checkout-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 15px;
}

.qb-summary-row {
  font-size: 14px;
}

.qb-summary-row b,
.qb-checkout-total strong {
  font-size: 20px;
}

.qb-cart-summary > p {
  font-size: 11px;
  line-height: 1.7;
  color: var(--muted);
  margin: 12px 0 17px;
}

.qb-full-button {
  width: 100%;
}

.qb-empty-cart {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: var(--orange);
  padding: 30px 5px;
}

.qb-empty-cart h3 {
  margin: 17px 0 6px;
  color: var(--ink);
}

.qb-empty-cart p {
  margin: 0 0 20px;
  font-size: 12px;
  line-height: 1.6;
  color: var(--muted);
}

.qb-back-button {
  align-self: flex-start;
  border: 0;
  background: transparent;
  padding: 18px 0;
  color: var(--orange-dark);
  font-size: 12px;
  font-weight: 800;
}

.qb-checkout-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-top: 8px;
}

.qb-checkout-form h3 {
  margin: 5px 0 0;
  font-size: 15px;
}

.qb-checkout-form > label:not(.qb-payment-option) {
  display: flex;
  flex-direction: column;
  gap: 7px;
  font-size: 12px;
  font-weight: 750;
}

.qb-checkout-form input:not([type="radio"]),
.qb-checkout-form textarea {
  width: 100%;
  padding: 12px;
  min-height: 44px;
  border: 1px solid #e4ddd4;
  border-radius: 10px;
  background: #fff;
  color: var(--ink);
  font-size: 12px;
  outline: none;
}

.qb-checkout-form input:focus,
.qb-checkout-form textarea:focus {
  border-color: var(--orange);
  box-shadow: 0 0 0 3px #f26a2115;
}

.qb-checkout-form textarea {
  resize: vertical;
}

.qb-payment-option {
  display: flex;
  gap: 10px;
  padding: 13px;
  border-radius: 11px;
  border: 1px solid var(--line);
  cursor: pointer;
}

.qb-payment-option:has(input:checked) {
  border-color: var(--orange);
  background: #fffaf5;
}

.qb-payment-option input {
  accent-color: var(--orange);
}

.qb-payment-option span {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.qb-payment-option b {
  font-size: 12px;
}

.qb-payment-option small {
  font-size: 10px;
  color: var(--muted);
  line-height: 1.5;
}

.qb-checkout-total {
  padding-top: 16px;
  border-top: 1px solid var(--line);
  margin-top: 5px;
}

.qb-success {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 25px 0;
}

.qb-success-icon {
  width: 70px;
  height: 70px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #e9f8e9;
  color: #268b40;
  font-size: 36px;
  font-weight: 800;
}

.qb-success h3 {
  font-size: 22px;
  margin: 22px 0 6px;
}

.qb-success > p {
  color: var(--muted);
  font-size: 12px;
  line-height: 1.7;
}

.qb-order-number {
  width: 100%;
  padding: 17px;
  margin: 12px 0;
  border: 1px dashed #e5c2a8;
  border-radius: 12px;
  background: #fff9f3;
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.qb-order-number small {
  color: var(--muted);
  font-size: 10px;
  letter-spacing: 1px;
}

.qb-order-number b {
  font-size: 19px;
}

.qb-small-note {
  font-size: 11px !important;
  margin-bottom: 24px;
}

.qb-drawer-error {
  margin-top: 15px;
}

@media (max-width: 1000px) {
  .qb-hero {
    margin: 0 16px;
    padding: 35px 28px;
  }

  .qb-hero h1 {
    letter-spacing: -2px;
  }

  .qb-product-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .qb-benefits {
    margin-left: 16px;
    margin-right: 16px;
  }

  .qb-featured-grid {
    gap: 12px;
  }

  .qb-featured-card > img,
  .qb-featured-card > .qb-food-placeholder {
    height: 150px;
  }
}

@media (max-width: 720px) {
  .qb-header {
    padding: 14px 16px;
    min-height: 72px;
  }

  .qb-logo {
    font-size: 21px;
  }

  .qb-logo-icon {
    width: 37px;
    height: 37px;
  }

  .qb-nav {
    display: none;
  }

  .qb-cart-button {
    padding: 9px;
  }

  .qb-hero {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    padding: 34px 23px 18px;
    border-radius: 20px;
  }

  .qb-hero h1 {
    font-size: 53px;
    margin-top: 19px;
  }

  .qb-hero-copy > p {
    font-size: 13px;
  }

  .qb-hero-actions {
    gap: 18px;
    margin-top: 22px;
  }

  .qb-hero-stats {
    gap: 22px;
    margin-top: 27px;
  }

  .qb-hero-visual {
    min-height: 305px;
    margin-top: 18px;
  }

  .qb-hero-circle {
    width: 260px;
  }

  .qb-hero-food {
    width: 265px;
  }

  .qb-floating-top {
    right: 0;
    top: 15px;
  }

  .qb-floating-bottom {
    left: 0;
    bottom: 10px;
  }

  .qb-floating-card {
    padding: 10px;
  }

  .qb-benefits {
    margin-top: 16px;
    padding: 18px;
    grid-template-columns: 1fr;
    gap: 17px;
  }

  .qb-benefits > div {
    justify-content: flex-start;
  }

  .qb-menu-section {
    padding: 55px 16px 65px;
  }

  .qb-menu-heading {
    align-items: stretch;
    flex-direction: column;
    gap: 20px;
  }

  .qb-menu-heading h2 {
    font-size: 32px;
  }

  .qb-search {
    width: 100%;
  }

  .qb-categories {
    flex-wrap: nowrap;
    overflow-x: auto;
    padding-bottom: 7px;
  }

  .qb-categories button {
    white-space: nowrap;
  }

  .qb-featured-grid {
    grid-template-columns: 1fr;
  }

  .qb-featured-card {
    display: grid;
    grid-template-columns: 115px minmax(0, 1fr);
    min-height: 130px;
  }

  .qb-featured-card > img,
  .qb-featured-card > .qb-food-placeholder {
    height: 100%;
    min-height: 130px;
  }

  .qb-featured-info {
    align-self: center;
  }

  .qb-product-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .qb-product-image {
    height: 140px;
  }

  .qb-product-info {
    padding: 11px;
  }

  .qb-product-info h4 {
    font-size: 13px;
  }

  .qb-product-info > p {
    min-height: 52px;
    font-size: 10px;
  }

  .qb-product-bottom {
    align-items: flex-start;
    flex-direction: column;
  }

  .qb-product-bottom > strong {
    font-size: 14px;
  }

  .qb-add-button {
    width: 100%;
  }

  .qb-footer {
    padding: 25px 16px;
    flex-direction: column;
    align-items: flex-start;
    gap: 9px;
  }

  .qb-footer p {
    margin: 0;
  }

  .qb-drawer {
    padding: 19px;
  }
}

@media (max-width: 360px) {
  .qb-product-grid {
    grid-template-columns: 1fr;
  }

  .qb-product-image {
    height: 200px;
  }

  .qb-product-bottom {
    align-items: center;
    flex-direction: row;
  }
}
</style>