document.addEventListener('DOMContentLoaded', function() {
    const planCards = document.querySelectorAll('.plan-card');
    const customizationSection = document.getElementById('customization');
    const selectedPlanName = document.getElementById('selected-plan-name');
    const backToPlansBtn = document.getElementById('back-to-plans');
    const membershipForm = document.getElementById('membership-form');
    const planTypeInput = document.getElementById('plan-type');
    const basePriceInput = document.getElementById('base-price');
    
    // Summary elements
    const summaryPlanName = document.getElementById('summary-plan-name');
    const summaryBasePrice = document.getElementById('summary-base-price');
    const summaryAddonsPrice = document.getElementById('summary-addons-price');
    const summaryTotalPrice = document.getElementById('summary-total-price');
    
    // Add-on checkboxes
    const addonCheckboxes = document.querySelectorAll('input[name="addons[]"]');
    
    // Plan prices
    const planPrices = {
        'basic': 29,
        'premium': 49,
        'elite': 79
    };
    
    // Plan selection
    planCards.forEach(card => {
        const selectBtn = card.querySelector('.select-plan');
        
        selectBtn.addEventListener('click', function() {
            const planType = this.getAttribute('data-plan');
            const planPrice = planPrices[planType];
            let planDisplayName;
            
            switch(planType) {
                case 'basic':
                    planDisplayName = 'Basic Fitness';
                    break;
                case 'premium':
                    planDisplayName = 'Premium Fitness';
                    break;
                case 'elite':
                    planDisplayName = 'Elite Fitness';
                    break;
                default:
                    planDisplayName = 'Selected Plan';
            }
            
            // Update plan name in customization section
            selectedPlanName.textContent = planDisplayName;
            
            // Update form hidden inputs
            planTypeInput.value = planType;
            basePriceInput.value = planPrice;
            
            // Update summary
            summaryPlanName.textContent = planDisplayName;
            summaryBasePrice.textContent = `$${planPrice}`;
            updateTotalPrice();
            
            // Show customization section
            window.scrollTo({ top: 0, behavior: 'smooth' });
            customizationSection.classList.remove('hidden');
            document.querySelector('.membership-plans').classList.add('hidden');
        });
    });
    
    // Back to plans button
    backToPlansBtn.addEventListener('click', function() {
        customizationSection.classList.add('hidden');
        document.querySelector('.membership-plans').classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    
    // Add-on selection
    addonCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateTotalPrice();
        });
    });
    
    // Calculate total price
    function updateTotalPrice() {
        const basePrice = parseFloat(basePriceInput.value) || 0;
        let addonsTotal = 0;
        
        addonCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                addonsTotal += parseFloat(checkbox.getAttribute('data-price')) || 0;
            }
        });
        
        summaryAddonsPrice.textContent = `$${addonsTotal}`;
        const total = basePrice + addonsTotal;
        summaryTotalPrice.textContent = `$${total}`;
    }
    
    // Payment method selection
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const cardDetails = document.getElementById('card-details');
    
    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            if (this.value === 'paypal') {
                cardDetails.style.display = 'none';
            } else {
                cardDetails.style.display = 'block';
            }
        });
    });
    
    // Form validation
    membershipForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        let isValid = true;
        const requiredFields = this.querySelectorAll('input[required]');
        
        // Reset previous error states
        document.querySelectorAll('.form-group.error').forEach(group => {
            group.classList.remove('error');
        });
        
        document.querySelectorAll('.error-message').forEach(msg => {
            msg.remove();
        });
        
        // Validate required fields
        requiredFields.forEach(field => {
            const parentGroup = field.closest('.form-group');
            
            if (!field.value.trim()) {
                isValid = false;
                parentGroup.classList.add('error');
                
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.innerText = 'This field is required';
                parentGroup.appendChild(errorMsg);
            }
        });
        
        // Validate email format
        const emailField = document.getElementById('email');
        if (emailField.value && !isValidEmail(emailField.value)) {
            isValid = false;
            const parentGroup = emailField.closest('.form-group');
            parentGroup.classList.add('error');
            
            const errorMsg = document.createElement('div');
            errorMsg.className = 'error-message';
            errorMsg.innerText = 'Please enter a valid email address';
            parentGroup.appendChild(errorMsg);
        }
        
        // Validate phone format
        const phoneField = document.getElementById('phone');
        if (phoneField.value && !isValidPhone(phoneField.value)) {
            isValid = false;
            const parentGroup = phoneField.closest('.form-group');
            parentGroup.classList.add('error');
            
            const errorMsg = document.createElement('div');
            errorMsg.className = 'error-message';
            errorMsg.innerText = 'Please enter a valid phone number';
            parentGroup.appendChild(errorMsg);
        }
        
        // If payment method is credit/debit card, validate card details
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
        if (paymentMethod !== 'paypal') {
            const cardFields = document.querySelectorAll('#card-details input');
            
            cardFields.forEach(field => {
                const parentGroup = field.closest('.form-group');
                
                if (!field.value.trim()) {
                    isValid = false;
                    parentGroup.classList.add('error');
                    
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'error-message';
                    errorMsg.innerText = 'This field is required';
                    parentGroup.appendChild(errorMsg);
                }
            });
            
            // Validate card number format
            const cardNumberField = document.getElementById('card-number');
            if (cardNumberField.value && !isValidCardNumber(cardNumberField.value)) {
                isValid = false;
                const parentGroup = cardNumberField.closest('.form-group');
                parentGroup.classList.add('error');
                
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.innerText = 'Please enter a valid card number';
                parentGroup.appendChild(errorMsg);
            }
            
            // Validate expiry date format
            const expiryField = document.getElementById('expiry');
            if (expiryField.value && !isValidExpiry(expiryField.value)) {
                isValid = false;
                const parentGroup = expiryField.closest('.form-group');
                parentGroup.classList.add('error');
                
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.innerText = 'Please enter a valid expiry date (MM/YY)';
                parentGroup.appendChild(errorMsg);
            }
            
            // Validate CVV format
            const cvvField = document.getElementById('cvv');
            if (cvvField.value && !isValidCVV(cvvField.value)) {
                isValid = false;
                const parentGroup = cvvField.closest('.form-group');
                parentGroup.classList.add('error');
                
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.innerText = 'Please enter a valid CVV';
                parentGroup.appendChild(errorMsg);
            }
        }
        
        // If form is valid, submit to server
        if (isValid) {
            // For demo purposes, we'll submit the form via AJAX
            const formData = new FormData(this);
            
            fetch('process_membership.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Display success message
                    const successMsg = document.createElement('div');
                    successMsg.className = 'success-message';
                    successMsg.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                    
                    membershipForm.insertBefore(successMsg, membershipForm.firstChild);
                    
                    // Reset form
                    membershipForm.reset();
                    
                    // Scroll to top of form
                    window.scrollTo({ top: customizationSection.offsetTop, behavior: 'smooth' });
                    
                    // Redirect to thank you page after a delay
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 3000);
                } else {
                    // Display error message
                    alert(data.message || 'An error occurred. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred. Please try again later.');
            });
        } else {
            // Scroll to first error
            const firstError = document.querySelector('.form-group.error');
            if (firstError) {
                window.scrollTo({ top: firstError.offsetTop - 100, behavior: 'smooth' });
            }
        }
    });
    
    // Validation helper functions
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    function isValidPhone(phone) {
        const re = /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/;
        return re.test(String(phone));
    }
    
    function isValidCardNumber(number) {
        // Basic validation - remove spaces and check if it's 13-19 digits
        const sanitized = number.replace(/\s+/g, '');
        return /^[0-9]{13,19}$/.test(sanitized);
    }
    
    function isValidExpiry(expiry) {
        // Format MM/YY
        return /^(0[1-9]|1[0-2])\/([0-9]{2})$/.test(expiry);
    }
    
    function isValidCVV(cvv) {
        // 3-4 digits
        return /^[0-9]{3,4}$/.test(cvv);
    }
    
    // Format input values
    const cardNumberField = document.getElementById('card-number');
    if (cardNumberField) {
        cardNumberField.addEventListener('input', function(e) {
            // Format card number with spaces every 4 digits
            let value = this.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = '';
            
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            
            this.value = formattedValue;
        });
    }
    
    const expiryField = document.getElementById('expiry');
    if (expiryField) {
        expiryField.addEventListener('input', function(e) {
            // Format expiry as MM/YY
            let value = this.value.replace(/\D/g, '');
            
            if (value.length > 2) {
                this.value = value.substring(0, 2) + '/' + value.substring(2, 4);
            } else {
                this.value = value;
            }
        });
    }
});