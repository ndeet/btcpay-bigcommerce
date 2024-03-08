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
                        if (radio.nextElementSibling && radio.nextElementSibling.innerText.includes('Bitcoin') && radio.checked) {
                            // Bitcoin option selected
                            const paymentButton = document.getElementById('checkout-payment-continue');
                            if (paymentButton) {
                                paymentButton.textContent = 'Pay with Bitcoin';
                                paymentButton.onclick = (event) => {
                                    event.preventDefault(); // Prevent default form submission

                                    const bcData = getBCData();

                                    getCart().then(cart => {
                                        fetch(bcData.proxyService + '/api/create-invoice', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                storeId: bcData.storeId,
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

// Function to get the cart data
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

const getBCData = () => {
    const thisScript = document.currentScript;
    const script_url = thisScript.src;
    const url = new URL(script_url);
    const storeId = url.searchParams.get('bcid');
    return {
        storeId: storeId,
        proxyService: 'https://' + url.hostname
    }
}

const getBTCPayData = () => {
    // todo: get data via proxy service, or find a another way.
    return {
        btcpayUrl: 'https://testnet.demo.btcpay.tech'
    }
}

//console.log(storeId);
// Configuration for the observer
//const config = { childList: true, subtree: true };

// Start observing the body element
//observer.observe(document.body, config);

// Show BTCPay modal.
const showBTCPayModal = function(data) {
    console.log('Triggered showBTCPayModal()');

    if (data.invoiceId == undefined) {
        //submitError(BTCPayWP.textModalClosed);
        console.error('No invoice id provided, aborting.');
    }
    const btcpayData = getBTCPayData();
    window.btcpay.setApiUrlPrefix(btcpayData.btcpayUrl);
    window.btcpay.showInvoice(data.invoiceId);

    let invoice_paid = false;
    window.btcpay.onModalReceiveMessage(function (event) {
        if (isObject(event.data)) {
            //console.log('BTCPay modal event: invoiceId: ' + event.data.invoiceId);
            //console.log('BTCPay modal event: status: ' + event.data.status);
            if (event.data.status) {
                switch (event.data.status) {
                    case 'complete':
                    case 'paid':
                        invoice_paid = true;
                        window.location = data.orderCompleteLink;
                        break;
                    case 'expired':
                        window.btcpay.hideFrame();
                        // submitError(BTCPayWP.textInvoiceExpired);
                        console.error('Invoice expired.')
                        break;
                }
            }
        } else { // handle event.data "loaded" "closed"
            if (event.data === 'close') {
                if (invoice_paid === true) {
                    window.location = data.orderCompleteLink;
                }
                // submitError(BTCPayWP.textModalClosed);
                console.error('Modal closed.')
            }
        }
    });
    const isObject = obj => {
        return Object.prototype.toString.call(obj) === '[object Object]'
    }
};

const loadModalScript = () => {
    const btcpay = getBTCPayData();
    const script = document.createElement('script');
    script.src = btcpay.btcpayUrl + '/modal/btcpay.js';
    document.head.appendChild(script);

    // Optional: Handle loading and error events
    script.onload = function() {
        console.log('External modal script loaded successfully.');
    };

    script.onerror = function() {
        console.error('Error loading the external modal script.');
    };

}


// Entrypoint.
loadModalScript();
observePaymentOptions();

