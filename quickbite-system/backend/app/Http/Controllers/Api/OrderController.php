
<template>
  <div class="checkout-container">
    <button class="back-button" type="button" @click="$router.back()">
      ← Back to cart
    </button>

    <h2>Checkout</h2>

    <div v-if="errorMessage" class="error-message">
      {{ errorMessage }}
    </div>

    <div v-if="successMessage" class="success-message">
      {{ successMessage }}
      <div v-if="orderNumber">
        Order number: <strong>{{ orderNumber }}</strong>
      </div>
    </div>

    <form @submit.prevent="placeOrder">
      <h3>Delivery details</h3>

      <label for="customerName">Full name</label>
      <input
        id="customerName"
        v-model.trim="form.full_name"
        type="text"
        maxlength="120"
        required
      />

      <label for="email">Email (optional)</label>
      <input
        id="email"
        v-model.trim="form.email"
        type="email"
        maxlength="180"
      />

      <label for="phone">Phone number</label>
      <input
        id="phone"
        v-model.trim="form.phone_number"
        type="tel"
        maxlength="40"
        required
      />

      <label for="address">Delivery address</label>
      <textarea
        id="address"
        v-model.trim="form.delivery_address"
        maxlength="500"
        :required="form.order_type === 'delivery'"
      ></textarea>

      <h3>Order type</h3>

      <label class="option">
        <input
          v-model="form.order_type"
          type="radio"
          value="delivery"
        />
        Delivery (₱50 delivery fee)
      </label>

      <label class="option">
        <input
          v-model="form.order_type"
          type="radio"
          value="pickup"
        />
        Pickup (no delivery fee)
      </label>

      <h3>Payment method</h3>

      <label class="option">
        <input
          v-model="form.payment_method"
          type="radio"
          value="cod"
        />
        Cash on delivery
      </label>

      <label class="option">
        <input
          v-model="form.payment_method"
          type="radio"
          value="gcash"
        />
        GCash
      </label>

      <label for="notes">Order notes (optional)</label>
      <textarea
        id="notes"
        v-model.trim="form.notes"
        maxlength="1000"
        placeholder="Any special instructions?"
      ></textarea>

      <h3>Order summary</h3>

      <div v-if="cartItems.length === 0" class="empty-cart">
        Your cart is empty. Please add products before checking out.
      </div>

      <div v-else>
        <div
          v-for="item in cartItems"
          :key="item.product_id"
          class="cart-item"
        >
          <span>
            {{ item.name || item.product_name || 'Product' }}
            × {{ item.quantity }}
          </span>

          <strong>
            {{ formatCurrency(itemPrice(item) * item.quantity) }}
          </strong>
        </div>

        <div class="summary-row">
          <span>Subtotal</span>
          <strong>{{ formatCurrency(subtotal) }}</strong>
        </div>

        <div class="summary-row">
          <span>Delivery fee</span>
          <strong>{{ formatCurrency(deliveryFee) }}</strong>
        </div>

        <div class="summary-row grand-total">
          <span>Total</span>
          <strong>{{ formatCurrency(grandTotal) }}</strong>
        </div>
      </div>

      <button
        type="submit"
        class="submit-button"
        :disabled="loading || cartItems.length === 0"
      >
        {{ loading ? 'Placing order...' : 'Place order' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import axios from 'axios'

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const orderNumber = ref('')

const form = reactive({
  full_name: '',
  email: '',
  phone_number: '',
  delivery_address: '',
  order_type: 'delivery',
  payment_method: 'cod',
  notes: ''
})

function readCart() {
  // Try common cart keys used by Vue projects.
  const keys = ['cart', 'cartItems', 'quickbite_cart']

  for (const key of keys) {
    try {
      const stored = localStorage.getItem(key)

      if (stored) {
        const parsed = JSON.parse(stored)

        if (Array.isArray(parsed)) {
          return parsed
        }

        if (Array.isArray(parsed.items)) {
          return parsed.items
        }
      }
    } catch (error) {
      console.warn(`Could not read cart key "${key}".`, error)
    }
  }

  return []
}

const cartItems = ref(readCart())

function itemPrice(item) {
  return Number(item.price ?? item.unit_price ?? 0)
}

const subtotal = computed(() =>
  cartItems.value.reduce(
    (sum, item) => sum + itemPrice(item) * Number(item.quantity || 0),
    0
  )
)

const deliveryFee = computed(() =>
  form.order_type === 'delivery' ? 50 : 0
)

const grandTotal = computed(() => subtotal.value + deliveryFee.value)

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP'
  }).format(amount)
}

async function placeOrder() {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''
  orderNumber.value = ''

  try {
    // Refresh the cart in case it changed before checkout.
    cartItems.value = readCart()

    if (cartItems.value.length === 0) {
      throw new Error('Your cart is empty. Please add products first.')
    }

    const items = cartItems.value.map((item) => ({
      product_id: Number(item.product_id ?? item.id),
      quantity: Number(item.quantity)
    }))

    if (items.some((item) =>
      !Number.isInteger(item.product_id) ||
      item.product_id < 1 ||
      !Number.isInteger(item.quantity) ||
      item.quantity < 1
    )) {
      throw new Error(
        'Some cart items are invalid. Please check your cart and try again.'
      )
    }

    const orderData = {
      customer_name: form.full_name,
      email: form.email || null,
      phone: form.phone_number,
      address: form.delivery_address,
      order_type: form.order_type,
      payment_method: form.payment_method,
      notes: form.notes || null,
      items
    }

    // Laravel API route: POST /api/orders
    const response = await axios.post('/api/orders', orderData)

    successMessage.value =
      response.data.message || 'Order placed successfully!'

    orderNumber.value = response.data.order?.order_number || ''

    // Clear only the cart key that was found after success.
    const keys = ['cart', 'cartItems', 'quickbite_cart']

    for (const key of keys) {
      try {
        const stored = localStorage.getItem(key)

        if (!stored) continue

        const parsed = JSON.parse(stored)

        if (Array.isArray(parsed) || Array.isArray(parsed?.items)) {
          localStorage.removeItem(key)
          break
        }
      } catch {
        // Ignore unrelated localStorage entries.
      }
    }

    cartItems.value = []
  } catch (error) {
    const errors = error.response?.data?.errors

    if (errors) {
      errorMessage.value = Object.values(errors).flat().join(' ')
    } else {
      errorMessage.value =
        error.response?.data?.message ||
        error.message ||
        'Unable to place your order. Please try again.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.checkout-container {
  max-width: 600px;
  margin: 30px auto;
  padding: 24px;
  font-family: Arial, sans-serif;
  color: #222;
}

h2 {
  margin: 12px 0 24px;
}

h3 {
  margin: 28px 0 16px;
}

label:not(.option) {
  display: block;
  margin: 16px 0 8px;
  font-weight: 600;
}

input:not([type='radio']),
textarea {
  box-sizing: border-box;
  width: 100%;
  padding: 14px;
  border: 1px solid #ddd;
  border-radius: 12px;
  font-size: 15px;
}

textarea {
  min-height: 90px;
  resize: vertical;
}

.option {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 12px 0;
  padding: 16px;
  border: 1px solid #eadfd5;
  border-radius: 12px;
  cursor: pointer;
}

.option input {
  accent-color: #f4511e;
}

.back-button {
  margin: 8px 0;
  padding: 8px 0;
  border: none;
  background: none;
  color: #f4511e;
  cursor: pointer;
}

.cart-item,
.summary-row {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0;
  border-bottom: 1px solid #eee;
}

.grand-total {
  margin-top: 8px;
  font-size: 19px;
  border-bottom: none;
}

.submit-button {
  width: 100%;
  margin-top: 24px;
  padding: 16px;
  border: none;
  border-radius: 12px;
  background: #ff641d;
  color: white;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
}

.submit-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.error-message,
.success-message,
.empty-cart {
  margin: 12px 0;
  padding: 14px;
  border-radius: 10px;
}

.error-message {
  background: #fff0f0;
  color: #c62828;
}

.success-message {
  background: #e8f5e9;
  color: #2e7d32;
}

.empty-cart {
  background: #fff8e1;
  color: #795548;
}
</style>