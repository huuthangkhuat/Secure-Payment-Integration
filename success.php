<?php
// Include configuration file
include 'config.php';

$is_gpay_payment = (isset($_GET['payment_method']) && $_GET['payment_method'] === 'gpay');
$is_card_payment = (isset($_GET['payment_method']) && $_GET['payment_method'] === 'stripe');
$is_stripe_embedded = (isset($_GET['session_id']) && !empty($_GET['session_id']));
$txn_id = '';
$payment_status = '';
$cart_items = [];
$grand_total = 0;
$customer_email = '';

// Handle PayPal transaction data from URL
if (!empty($_GET['tx']) && !empty($_GET['st']) && $_GET['st'] === 'Completed') {
    $txn_id = $_GET['tx'];
    $payment_status = $_GET['st'];
    $customer_email = isset($_GET['payer_email']) ? $_GET['payer_email'] : 'customer@paypal.com';
    
    $num_items = !empty($_GET['num_cart_items']) ? intval($_GET['num_cart_items']) : 0;
    
    for ($i = 1; $i <= $num_items; $i++) {
        if (!empty($_GET["item_name{$i}"]) && !empty($_GET["quantity{$i}"]) && !empty($_GET["mc_gross_{$i}"])) {
            $item_name = $_GET["item_name{$i}"];
            $quantity = intval($_GET["quantity{$i}"]);
            $price_per_item = floatval($_GET["mc_gross_{$i}"]);
            
            $cart_items[] = [
                'name' => $item_name,
                'quantity' => $quantity,
                'total_price' => $price_per_item
            ];
            $grand_total += $price_per_item;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateway</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet" />
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>

<div class="container" style="margin-top: 50px;">
    <div class="status text-center">
        <?php if ($is_stripe_embedded) { ?>
            <h1>Payment <span id="status">Processing</span></h1>
            <p id="message">We're confirming your payment...</p>
            <div id="spinner" class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <div id="unified-summary" style="display:none;">
                </div>
        <?php } else if (!empty($txn_id) && $payment_status === 'Completed') { ?>
            <h1 class="success">Your Payment has been Successful!</h1>
            <h4>Payment Summary</h4>
            <p><b>Transaction ID:</b> <?php echo htmlspecialchars($txn_id); ?></p>
            <p><b>Payment Status:</b> <?php echo htmlspecialchars($payment_status); ?></p>
            
            <h4 style="margin-top: 30px;">Order Details</h4>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Total Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                            <td>$<?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <h4 style="margin-top: 30px;">Grand Total: $<?php echo number_format($grand_total, 2); ?></h4>
            <p style="margin-top: 20px;">An email confirmation has been sent to <?php echo htmlspecialchars($customer_email); ?>.</p>
        <?php } else { ?>
            <h1 class="error">Your Payment has Failed!</h1>
            <p>Please check your payment details or try again later.</p>
            <div id="unified-summary-js" style="display:none;"></div>
        <?php } ?>
    </div>
    <div class="text-center" style="margin-top: 40px;">
        <a href="index.html" class="btn btn-primary">Back to Products</a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const isStripeEmbedded = <?php echo json_encode($is_stripe_embedded); ?>;
        const isGPayPayment = <?php echo json_encode($is_gpay_payment); ?>;
        const isCardPayment = <?php echo json_encode($is_card_payment); ?>;
        const savedCart = localStorage.getItem('shoppingCart');
        const cartItems = savedCart ? JSON.parse(savedCart) : {};

        function populateSummary(transactionId, paymentStatus, customerEmail, cartData) {
            let grandTotal = 0;
            let tableRows = '';

            for (const id in cartData) {
                const item = cartData[id];
                const itemTotal = item.price * item.qty;
                grandTotal += itemTotal;
                tableRows += `
                    <tr>
                        <td>${item.name}</td>
                        <td>${item.qty}</td>
                        <td>$${itemTotal.toFixed(2)}</td>
                    </tr>
                `;
            }

            const summaryHtml = `
                <h1 class="success">Your Payment has been Successful!</h1>
                <h4>Payment Summary</h4>
                <p><b>Transaction ID:</b> ${transactionId}</p>
                <p><b>Payment Status:</b> ${paymentStatus}</p>
                
                <h4 style="margin-top: 30px;">Order Details</h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Total Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tableRows}
                    </tbody>
                </table>
                <h4 style="margin-top: 30px;">Grand Total: $${grandTotal.toFixed(2)}</h4>
                <p style="margin-top: 20px;">An email confirmation has been sent to ${customerEmail}.</p>
            `;
            return summaryHtml;
        }

        if (isStripeEmbedded) {
            const urlParams = new URLSearchParams(window.location.search);
            const sessionId = urlParams.get('session_id');
            const statusElement = document.getElementById('status');
            const messageElement = document.getElementById('message');
            const spinnerElement = document.getElementById('spinner');
            const unifiedSummaryDiv = document.getElementById('unified-summary');

            if (sessionId) {
                fetch(`http://localhost:4242/session-status?session_id=${sessionId}`)
                    .then(response => response.json())
                    .then(session => {
                        if (session.status === 'complete') {
                            statusElement.textContent = 'Successful';
                            statusElement.className = 'success';
                            messageElement.textContent = 'Thank you for your purchase!';
                            spinnerElement.style.display = 'none';
                            unifiedSummaryDiv.style.display = 'block';

                            const summaryHtml = populateSummary(
                                `STRIPE_TXN_${sessionId.substring(5, 15)}`,
                                'Completed',
                                session.customer_email,
                                cartItems
                            );
                            unifiedSummaryDiv.innerHTML = summaryHtml;

                        } else {
                            statusElement.textContent = 'Failed';
                            statusElement.className = 'error';
                            messageElement.textContent = 'Payment was not successful. Please try again.';
                            spinnerElement.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        statusElement.textContent = 'Error';
                        statusElement.className = 'error';
                        messageElement.textContent = 'An error occurred while checking the payment status.';
                        spinnerElement.style.display = 'none';
                    });
            } else {
                statusElement.textContent = 'Error';
                statusElement.className = 'error';
                messageElement.textContent = 'No session ID found in the URL.';
                spinnerElement.style.display = 'none';
            }
        } else if (isGPayPayment || isCardPayment) {
            const paymentType = isGPayPayment ? 'Google Pay' : 'Credit Card';
            const customerEmail = 'customer@example.com';
            const transactionId = `${paymentType.toUpperCase()}_TXN_${Math.random().toString(16).slice(2, 10).toUpperCase()}`;

            const summaryHtml = populateSummary(
                transactionId,
                'Completed',
                customerEmail,
                cartItems
            );
            document.getElementById('unified-summary-js').innerHTML = summaryHtml;
            document.getElementById('unified-summary-js').style.display = 'block';

            document.querySelector('.status h1.error').style.display = 'none';
            document.querySelector('.status p').style.display = 'none';
        }

        // Clear the cart from local storage after payment processing is complete
        localStorage.removeItem('shoppingCart');
    });
</script>

</body>
</html>