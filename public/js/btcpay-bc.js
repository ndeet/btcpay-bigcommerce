// Function to initialize the observer
function observePaymentOptions() {
    // Observer callback function
    const observerCallback = (mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList' && mutation.addedNodes.length) {
                // Check if the checkout form is available in the document
                const checkoutForm = document.querySelector('.checkout-form');
                if (checkoutForm) {
                    // Iterate over radio buttons to check their state
                    checkoutForm.querySelectorAll('input[name="paymentProviderRadio"]').forEach(radio => {
                        if (radio.nextElementSibling && radio.nextElementSibling.innerText.includes('Bitcoin / Lightning Network') && radio.checked) {
                            // Bitcoin option selected
                            const paymentButton = document.getElementById('checkout-payment-continue');
                            if (paymentButton) {
                                paymentButton.textContent = 'Pay with Bitcoin';
                                paymentButton.onclick = (event) => {
                                    event.preventDefault(); // Prevent default form submission

                                    getCart().then(cart => {
                                        fetch('https://bigcommerce.btcpay.tech/api/create-invoice', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                storeId: storeId,
                                                cartId: cart.id,
                                                currency: cart.currency,
                                                total: cart.amount,
                                                email: cart.customerEmail
                                            })
                                        })
                                            .then(response => response.json())
                                            .then(data => {
                                                console.warn('Payment initiation successful:', data);
                                                if (data.checkoutLink) {
                                                    window.location.href = data.checkoutLink;
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Payment initiation failed:', error);
                                            });
                                    }).catch(error => {
                                        console.error('Failed to get cart:', error);
                                    });
                                };
                            }
                        } else if (radio.nextElementSibling && !radio.nextElementSibling.innerText.includes('Bitcoin / Lightning Network') && radio.checked) {
                            // Non-Bitcoin option selected
                            const paymentButton = document.getElementById('checkout-payment-continue');
                            if (paymentButton) {
                                paymentButton.textContent = 'Place Order';
                                paymentButton.onclick = null; // Reset to default behavior
                            }
                        }
                    });
                }
            }
        });
    };

    // Create a new observer instance
    const observer = new MutationObserver(observerCallback);

    // Configuration of the observer:
    const config = { childList: true, subtree: true };

    // Start observing the entire document for changes
    observer.observe(document, config);
}

// Start observing as soon as the script is loaded
observePaymentOptions();


const getCart = () => {
    // Return the fetch promise
    return fetch('/api/storefront/carts', {
        credentials: 'include'
    })
        .then(response => response.json())
        .then(myJson => {
            const cart = {
                id: myJson[0].id,
                currency: myJson[0].currency.code,
                amount: myJson[0].cartAmount,
                customerEmail: myJson[0].email
            };
            console.log('Log Cart');
            console.table(cart);
            // Resolve the promise with the cart data
            return cart;
        })
        .catch(error => {
            console.error('Error fetching cart:', error);
            // You might want to handle the rejected state by returning a default value or re-throwing the error
            throw error;
        });
}

const getBCId = () => {
    const thisScript = document.currentScript;
    const script_url = thisScript.src;
    const id = script_url.split('=')[1];
    return id;
}

const storeId = getBCId();
//console.log(storeId);
// Configuration for the observer
//const config = { childList: true, subtree: true };

// Start observing the body element
//observer.observe(document.body, config);
