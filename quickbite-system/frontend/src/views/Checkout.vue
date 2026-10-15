
<template>
  <div class="checkout-page">
    <div class="checkout-container">
      <button type="button" class="back-button" @click="goBack">
        ← Back to cart
      </button>

      <h1>Checkout</h1>

      <div v-if="errorMessage" class="alert error">
        {{ errorMessage }}
      </div>

      <div v-if="successMessage" class="alert success">
        {{ successMessage }}
      </div>

      <form @submit.prevent="placeOrder">
        <section class="section">
          <h2>Delivery details</h2>

          <div class="form-group">
            <label for="customer_name">Full name</label>
            <input
              id="customer_name"
              v-model.trim="form.customer_name"
              type="text"
              placeholder="Enter your full name"
              required
            />
          </div>

          <div class="form-group">
            <label for="phone">Phone number</label>
            <input
              id="phone"
              v-model.trim="form.phone"
              type="tel"
              placeholder="Enter your phone number"
              required
            />
          </div>

          <div class="form-group">
            <label for="delivery_address">Delivery address</label>
            <textarea
              id="delivery_address"
              v-model.trim="form.delivery_address"
              placeholder="Enter your complete delivery address"
              rows="3"
              required
            ></textarea>
          </div>
        </section>

        <section class="section">
          <h2>Payment method</h2>

          <label
            class="payment-option"
            :class="{ selected: form.payment_method === 'cod' }"
          >
            <input
              v-model="form.payment_method"
              type="radio"
              value="cod"
            />

            <span>
              <strong>Cash on delivery</strong>
              <small>Pay when your order arrives.</small>
            </span>
          </label>

          <label
            class="payment-option"
            :class="{ selected: form.payment_method === 'gcash' }"
          >
            <input
              v-model="form.payment_method"
              type="radio"
              value="gcash"
            />

            <span>
              <strong>GCash</strong>
              <small>
                Follow the store's payment instructions after placing your
                order.
              </small>
            </span>
          </label>
        </section>

        <section class="section">
          <h2>Special instructions</h2>

          <textarea
            v-model.trim="form.special_instructions"
            placeholder="Example: Please don't add onions."
            rows="3"
          ></textarea>

          <small class="hint">Optional</small>
        </section>

        <section class="section">
          <h2>Order summary</h2>

          <div v-if="cartItems.length === 0" class="empty-cart">
            Your cart is empty.
          </div>

          <div
            v-for="(item, index) in cartItems"
            :key="item.id ?? item.product_id ?? index"
            class="cart-item"
          >
            <div>
              <strong>{{ item.name ?? item.product_name }}</strong>
              <small>Quantity: {{ item.quantity }}</small>
            </div>

            <span>
              {{ money(priceOf(item) * Number(item.quantity || 0)) }}
            </span>
          </div>

          <div class="total-row">
            <strong>Total</strong>
            <strong>{{ money(total) }}</strong>
          </div>
        </section>

        <button
          class="place-order-button"
          type="submit"
          :disabled="isSubmitting || cartItems.length === 0"
        >
          {{
            isSubmitting
              ? "Placing order..."
              : `Place my order · ${money(total)}`
          }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();

const errorMessage = ref("");
const successMessage = ref("");
const isSubmitting = ref(false);
const cartItems = ref([]);

const form = reactive({
  customer_name: "",
  phone: "",
  delivery_address: "",
  payment_method: "cod",
  special_instructions: "",
});

// Change this URL if your Laravel API uses a different endpoint.
const API_URL = "http://127.0.0.1:8000/api/orders";

onMounted(() => {
  loadCart();
});

function loadCart() {
  try {
    const savedCart = localStorage.getItem("cart");

    if (savedCart) {
      const parsed = JSON.parse(savedCart);
      cartItems.value = Array.isArray(parsed) ? parsed : [];
    } else {
      cartItems.value = [];
    }
  } catch (error) {
    console.error("Error loading cart:", error);
    errorMessage.value = "Unable to load your cart.";
  }
}

function priceOf(item) {
  return Number(item.price ?? item.product_price ?? 0);
}

const total = computed(() =>
  cartItems.value.reduce(
    (sum, item) =>
      sum + priceOf(item) * Number(item.quantity || 0),
    0
  )
);

function money(amount) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
  }).format(Number(amount) || 0);
}

function goBack() {
  router.push("/cart");
}

async function placeOrder() {
  errorMessage.value = "";
  successMessage.value = "";

  if (!form.customer_name || !form.phone || !form.delivery_address) {
    errorMessage.value = "Please complete all delivery details.";
    return;
  }

  if (cartItems.value.length === 0) {
    errorMessage.value = "Your cart is empty.";
    return;
  }

  /*
   * IMPORTANT:
   * The backend requires order_type.
   * This example uses "delivery" because this checkout is for delivery.
   */
  const payload = {
    customer_name: form.customer_name,
    phone: form.phone,
    delivery_address: form.delivery_address,

    order_type: "delivery",

    payment_method: form.payment_method,
    special_instructions: form.special_instructions || null,

    items: cartItems.value.map((item) => ({
      product_id: item.product_id ?? item.id,
      quantity: Number(item.quantity),
    })),
  };

  // Check the payload in the browser console if validation still fails.
  console.log("Order payload being sent:", payload);

  isSubmitting.value = true;

  try {
    const response = await fetch(API_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(payload),
    });

    const result = await response.json().catch(() => ({}));

    if (!response.ok) {
      console.error("Laravel order error:", result);

      if (result.errors) {
        errorMessage.value = Object.values(result.errors)
          .flat()
          .join(" ");
      } else {
        errorMessage.value =
          result.message || "Failed to place your order.";
      }

      return;
    }

    successMessage.value = "Your order was placed successfully!";

    // Clear the cart only after Laravel confirms success.
    localStorage.removeItem("cart");
    cartItems.value = [];

    const orderId = result.order?.id ?? result.data?.id ?? result.id;

    if (orderId) {
      await router.push(`/confirmation/${orderId}`);
    } else {
      await router.push("/confirmation");
    }
  } catch (error) {
    console.error("Checkout request failed:", error);

    errorMessage.value =
      "Cannot connect to Laravel. Make sure your backend server is running.";
  } finally {
    isSubmitting.value = false;
  }
}
</script>

<style scoped>
* {
  box-sizing: border-box;
}

.checkout-page {
  min-height: 100vh;
  padding: 35px 18px;
  background: #f5f6fa;
  color: #242424;
}

.checkout-container {
  max-width: 760px;
  margin: auto;
  padding: 30px;
  border-radius: 16px;
  background: white;
  box-shadow: 0 5px 25px rgba(0, 0, 0, 0.07);
}

.back-button {
  margin-bottom: 18px;
  padding: 8px 0;
  border: 0;
  background: transparent;
  color: #444;
  cursor: pointer;
  font-size: 15px;
}

h1 {
  margin: 0 0 24px;
  font-size: 30px;
}

.section {
  margin-bottom: 25px;
  padding-bottom: 24px;
  border-bottom: 1px solid #eee;
}

.section h2 {
  margin: 0 0 18px;
  font-size: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 17px;
}

.form-group label {
  font-weight: 600;
}

input:not([type="radio"]),
textarea {
  width: 100%;
  padding: 13px;
  border: 1px solid #d8dce3;
  border-radius: 9px;
  outline: none;
  font: inherit;
}

input:focus,
textarea:focus {
  border-color: #ef6b32;
  box-shadow: 0 0 0 3px rgba(239, 107, 50, 0.12);
}

textarea {
  resize: vertical;
}

.payment-option {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 12px;
  padding: 16px;
  border: 1px solid #ddd;
  border-radius: 10px;
  cursor: pointer;
}

.payment-option input {
  margin-top: 4px;
  accent-color: #ef6b32;
}

.payment-option span {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.payment-option small,
.hint,
.cart-item small {
  color: #777;
  line-height: 1.5;
}

.payment-option.selected {
  border-color: #ef6b32;
  background: #fff8f4;
}

.cart-item,
.total-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  padding: 12px 0;
}

.cart-item > div {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.total-row {
  margin-top: 8px;
  padding-top: 18px;
  border-top: 1px solid #eee;
  font-size: 19px;
}

.place-order-button {
  width: 100%;
  padding: 15px;
  border: none;
  border-radius: 10px;
  background: #ef6b32;
  color: white;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
}

.place-order-button:hover {
  background: #d95720;
}

.place-order-button:disabled {
  background: #aaa;
  cursor: not-allowed;
}

.alert {
  margin-bottom: 18px;
  padding: 13px 15px;
  border-radius: 8px;
}

.error {
  background: #fff0f0;
  color: #b42318;
}

.success {
  background: #ecfdf3;
  color: #067647;
}

.empty-cart {
  padding: 12px 0;
  color: #777;
}

@media (max-width: 600px) {
  .checkout-container {
    padding: 20px;
  }

  h1 {
    font-size: 25px;
  }
}
</style>