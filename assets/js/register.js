document.addEventListener('DOMContentLoaded', function () {
  // Get elements
  const step1 = document.getElementById('step1');
  const step2 = document.getElementById('step2');
  const nextStep1Button = document.getElementById('nextStep1');
  const backStep2Button = document.getElementById('backStep2');
  const stepIndicators = document.querySelectorAll('.step');

  nextStep1Button.addEventListener('click', function () {
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();

    if (!username) {
      showError('Please enter your username');
      return;
    }

    if (!email) {
      showError('Please enter your email');
      return;
    }

    if (!validateEmail(email)) {
      showError('Please enter a valid email address');
      return;
    }

    step1.style.display = 'none';
    step2.style.display = 'flex';

    stepIndicators[0].classList.remove('active');
    stepIndicators[1].classList.add('active');
  });

  backStep2Button.addEventListener('click', function () {
    step2.style.display = 'none';
    step1.style.display = 'flex';

    stepIndicators[1].classList.remove('active');
    stepIndicators[0].classList.add('active');
  });

  function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  }

  function showError(message) {
    let errorElement = document.querySelector('.error-message');

    if (!errorElement) {
      errorElement = document.createElement('div');
      errorElement.className = 'error-message';
      const form = document.getElementById('registerForm');
      form.appendChild(errorElement);
    }

    errorElement.textContent = message;
    setTimeout(() => {
      errorElement.style.display = 'none';
    }, 3000);
  }
});
