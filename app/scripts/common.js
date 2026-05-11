let isDropdownOpen = false;

function updateMobileNavPosition() {
  const mobileNav = document.querySelector('.mobile-nav');
  if (!mobileNav || !document.body.classList.contains('ios-fixed-nav-fallback')) {
    return;
  }

  const navHeight = mobileNav.offsetHeight;
  const top = window.scrollY + window.innerHeight - navHeight;
  document.documentElement.style.setProperty('--mobile-nav-top', `${Math.max(0, top)}px`);
}

function enableIosMobileNavFallback() {
  const mobileNav = document.querySelector('.mobile-nav');
  if (!mobileNav) {
    return;
  }

  const ua = navigator.userAgent || '';
  const isIos = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const isWebKit = /WebKit/i.test(ua);

  if (!isIos || !isWebKit || window.innerWidth > 768) {
    document.body.classList.remove('ios-fixed-nav-fallback');
    document.documentElement.style.removeProperty('--mobile-nav-top');
    return;
  }

  document.body.classList.add('ios-fixed-nav-fallback');
  updateMobileNavPosition();

  const scheduleUpdate = () => window.requestAnimationFrame(updateMobileNavPosition);
  window.addEventListener('scroll', scheduleUpdate, { passive: true });
  window.addEventListener('resize', scheduleUpdate);

  if (window.visualViewport) {
    window.visualViewport.addEventListener('scroll', scheduleUpdate);
    window.visualViewport.addEventListener('resize', scheduleUpdate);
  }
}

function toggleDropdown() {
  const dropdown = document.querySelector('.dropdown');
  dropdown.classList.toggle('is-open');
  isDropdownOpen = !isDropdownOpen;
}

/**
 * Wrap a promise (typically a fetch) with a loading spinner overlay
 * inside the given DOM element. The spinner is removed when the
 * promise settles, regardless of success/failure.
 *
 *   withSpinner(fetch('/api'), document.querySelector('.subscriptions'))
 *     .then(...).catch(...)
 */
function withSpinner(promise, host) {
  if (!host) return promise;
  host.classList.add('mf-busy');
  const overlay = document.createElement('div');
  overlay.className = 'mf-spinner-overlay';
  host.appendChild(overlay);
  const cleanup = () => {
    host.classList.remove('mf-busy');
    if (overlay.parentNode === host) host.removeChild(overlay);
  };
  return Promise.resolve(promise).then(
    (v) => { cleanup(); return v; },
    (e) => { cleanup(); throw e; }
  );
}

/**
 * Wrap fetch() so that:
 *  - 401 responses redirect the user to login.php
 *  - true network failures (no response) raise a friendly toast
 * Other handling stays unchanged so callers see the response normally.
 */
function safeFetch(input, init) {
  return fetch(input, init).then((response) => {
    if (response && response.status === 401) {
      window.location.href = 'login.php';
      // Return a never-resolving promise so the caller's .then() doesn't
      // run during the redirect.
      return new Promise(() => {});
    }
    return response;
  }).catch((err) => {
    // Network-level failures don't even produce a Response. Show a toast
    // so the user knows it wasn't their action that failed silently.
    if (typeof showErrorMessage === 'function') {
      try {
        showErrorMessage('Network error. Check your connection and try again.', {
          retryLabel: 'Retry',
          onRetry: () => window.location.reload(),
        });
      } catch (_) {}
    }
    throw err;
  });
}

class ApiFetchError extends Error {
  constructor(message, response, data) {
    super(message);
    this.name = 'ApiFetchError';
    this.response = response;
    this.status = response ? response.status : 0;
    this.data = data || null;
  }
}

function translateOrDefault(key, fallback) {
  return typeof translate === 'function' ? translate(key) : fallback;
}

function apiFetch(input, init = {}, options = {}) {
  const expectJson = options.expectJson !== false;

  return safeFetch(input, init).then(async (response) => {
    const contentType = response.headers.get('content-type') || '';
    const isJson = contentType.includes('application/json');
    const data = isJson ? await response.json() : await response.text();

    if (response.status === 403 && typeof showErrorMessage === 'function') {
      showErrorMessage(
        (data && data.message) || translateOrDefault('error', 'Permission denied')
      );
    }

    if (!response.ok) {
      const message = (data && (data.message || data.title)) || translateOrDefault('network_response_error', 'Network response error');
      throw new ApiFetchError(message, response, data);
    }

    if (expectJson && !isJson) {
      throw new ApiFetchError(translateOrDefault('network_response_error', 'Expected JSON response'), response, data);
    }

    if (isJson && data && data.success === false) {
      const message = data.message || data.title || translateOrDefault('error', 'Error');
      throw new ApiFetchError(message, response, data);
    }

    return data;
  });
}

function apiErrorMessage(error, fallback) {
  if (error && error.data && (error.data.message || error.data.title)) {
    return error.data.message || error.data.title;
  }

  if (error && error.message) {
    return error.message;
  }

  return fallback;
}

function showErrorMessage(message, options = {}) {
  const toast = document.querySelector(".toast#errorToast");
  const closeIcon = document.querySelector(".close-error");
  const errorMessage = document.querySelector(".errorMessage");
  const progress = document.querySelector(".progress.error");
  let timer1, timer2;
  const oldRetry = toast.querySelector(".toast-retry");
  if (oldRetry) {
    oldRetry.remove();
  }
  errorMessage.textContent = message;
  if (options.onRetry) {
    const retryButton = document.createElement("button");
    retryButton.type = "button";
    retryButton.className = "toast-retry";
    retryButton.textContent = options.retryLabel || "Retry";
    retryButton.addEventListener("click", function () {
      toast.classList.remove("active");
      progress.classList.remove("active");
      options.onRetry();
    });
    errorMessage.insertAdjacentElement("afterend", retryButton);
  }
  toast.classList.add("active");
  progress.classList.add("active");
  timer1 = setTimeout(() => {
    toast.classList.remove("active");
    closeIcon.removeEventListener("click", () => { });
  }, 5000);

  timer2 = setTimeout(() => {
    progress.classList.remove("active");
  }, 5300);

  closeIcon.addEventListener("click", () => {
    toast.classList.remove("active");

    setTimeout(() => {
      progress.classList.remove("active");
    }, 300);

    clearTimeout(timer1);
    clearTimeout(timer2);
    closeIcon.removeEventListener("click", () => { });
  });
}

function showSuccessMessage(message) {
  const toast = document.querySelector(".toast#successToast");
  const closeIcon = document.querySelector(".close-success");
  const successMessage = document.querySelector(".successMessage");
  const progress = document.querySelector(".progress.success");
  let timer1, timer2;
  successMessage.textContent = message;
  toast.classList.add("active");
  progress.classList.add("active");
  timer1 = setTimeout(() => {
    toast.classList.remove("active");
    closeIcon.removeEventListener("click", () => { });
  }, 5000);

  timer2 = setTimeout(() => {
    progress.classList.remove("active");
  }, 5300);

  closeIcon.addEventListener("click", () => {
    toast.classList.remove("active");

    setTimeout(() => {
      progress.classList.remove("active");
    }, 300);

    clearTimeout(timer1);
    clearTimeout(timer2);
    closeIcon.removeEventListener("click", () => { });
  });
}

document.addEventListener('DOMContentLoaded', function () {

  const userLocale = navigator.language || navigator.languages[0];
  document.cookie = `user_locale=${userLocale}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;

  if (window.update_theme_settings) {
    const prefersDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const themePreference = prefersDarkMode ? 'dark' : 'light';
    const darkThemeCss = document.querySelector("#dark-theme");
    darkThemeCss.disabled = themePreference === 'light';

    // Preserve existing classes on the body tag
    const existingClasses = document.body.className.split(' ').filter(cls => cls !== 'dark' && cls !== 'light');
    document.body.className = [...existingClasses, themePreference].join(' ');

    document.cookie = `inUseTheme=${themePreference}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;
    const themeColorMetaTag = document.querySelector('meta[name="theme-color"]');
    themeColorMetaTag.setAttribute('content', themePreference === 'dark' ? '#222222' : '#FFFFFF');
  }

  document.addEventListener('mousedown', function (event) {
    var dropdown = document.querySelector('.dropdown');
    var dropdownContent = document.querySelector('.dropdown-content');

    if (!dropdown.contains(event.target) && isDropdownOpen) {
      dropdown.classList.remove('is-open');
      isDropdownOpen = false;
    }
  });

  document.querySelector('.dropdown-content').addEventListener('focus', function () {
    isDropdownOpen = true;
  });

  enableIosMobileNavFallback();
});

function getCookie(name) {
  const cookies = document.cookie.split(';');
  for (let cookie of cookies) {
    cookie = cookie.trim();
    if (cookie.startsWith(`${name}=`)) {
      return cookie.substring(name.length + 1);
    }
  }
  return null;
}
