const express = require('express');
const stripe = require('stripe')('sk-test-key'); // Replace with your Stripe secret key
const bodyParser = require('body-parser');
const cors = require('cors');
const app = express();
const port = process.env.PORT || 4242;

// Middleware to parse JSON bodies
app.use(bodyParser.json());
// Enable CORS for all routes
app.use(cors({
  origin: 'http://localhost', // Your Apache server's origin
  credentials: true
}));
// Serve static files from your current directory
app.use(express.static('.'));

// Endpoint to create a checkout session
app.post('/create-checkout-session', async (req, res) => {
  try {
    // Calculate total from cart
    const lineItems = [];
    
    for (const id in req.body.cart) {
      const item = req.body.cart[id];
      
      lineItems.push({
        price_data: {
          currency: 'aud',
          product_data: {
            name: item.name,
          },
          unit_amount: Math.round(item.price * 100), // Convert to cents
        },
        quantity: item.qty,
      });
    }
    

    // Create a Checkout Session with ui_mode: 'embedded' and return_url to success.php
    const session = await stripe.checkout.sessions.create({
      ui_mode: 'embedded',
      line_items: lineItems,
      mode: 'payment',
      return_url: `http://localhost/Assignment3/success.php?session_id={CHECKOUT_SESSION_ID}`,
    });

    // Return the client_secret instead of session.id
    res.json({ clientSecret: session.client_secret });
  } catch (error) {
    console.error('Error creating checkout session:', error);
    res.status(500).json({ error: error.message });
  }
});

// Add an endpoint to retrieve session status
app.get('/session-status', async (req, res) => {
  try {
    const session = await stripe.checkout.sessions.retrieve(req.query.session_id);

    res.send({
      status: session.status,
      customer_email: session.customer_details?.email || 'customer'
    });
  } catch (error) {
    console.error('Error retrieving session:', error);
    res.status(500).json({ error: error.message });
  }
});

app.listen(port, () => {
  console.log(`Server running on port ${port}`);
});