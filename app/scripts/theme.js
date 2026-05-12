function switchTheme() {
  const darkThemeCss = document.querySelector("#dark-theme");
  darkThemeCss.disabled = !darkThemeCss.disabled;

  const themeChoice = darkThemeCss.disabled ? 'light' : 'dark';
  document.cookie = 'theme=' + themeValue + '; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax';

  document.body.className = themeChoice;

  const button = document.getElementById("switchTheme");
  button.disabled = true;

  fetch('endpoints/settings/theme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ theme: themeChoice === 'dark' })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
      button.disabled = false;
    }).catch(error => {
      button.disabled = false;
    });
}

function setDarkTheme(theme) {
  const darkThemeButton = document.querySelector("#theme-dark");
  const lightThemeButton = document.querySelector("#theme-light");
  const automaticThemeButton = document.querySelector("#theme-automatic");
  const themeButtons = [
    darkThemeButton,
    lightThemeButton,
    automaticThemeButton,
    ...document.querySelectorAll(".header-theme-button")
  ].filter(Boolean);
  const darkThemeCss = document.querySelector("#dark-theme");
  const themes = { 0: 'light', 1: 'dark', 2: 'automatic' };
  const themeValue = themes[theme];
  const prefersDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;

  themeButtons.forEach(button => {
    button.disabled = true;
  });

  fetch('endpoints/settings/theme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ theme: theme })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        themeButtons.forEach(button => {
          button.disabled = false;
          button.classList.remove('selected');
        });

        document.cookie = `theme=${themeValue}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;
        const existingClasses = document.body.className.split(' ').filter(cls => cls !== 'dark' && cls !== 'light' && cls !== 'automatic');
        const setBodyTheme = (mode) => {
          document.body.className = [...existingClasses, mode].join(' ');
          const themeColorMetaTag = document.querySelector('meta[name="theme-color"]');
          if (themeColorMetaTag) {
            themeColorMetaTag.setAttribute('content', mode === 'dark' ? '#222222' : '#FFFFFF');
          }
        };

        if (theme == 0) {
          darkThemeCss.disabled = true;
          setBodyTheme('light');
          document.querySelectorAll('#theme-light, .header-theme-button[data-theme-mode="0"]').forEach(button => button.classList.add('selected'));
        }

        if (theme == 1) {
          darkThemeCss.disabled = false;
          setBodyTheme('dark');
          document.querySelectorAll('#theme-dark, .header-theme-button[data-theme-mode="1"]').forEach(button => button.classList.add('selected'));
        }

        if (theme == 2) {
          darkThemeCss.disabled = !prefersDarkMode;
          setBodyTheme(prefersDarkMode ? 'dark' : 'light');
          document.querySelectorAll('#theme-automatic, .header-theme-button[data-theme-mode="2"]').forEach(button => button.classList.add('selected'));
          document.cookie = `inUseTheme=${prefersDarkMode ? 'dark' : 'light'}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;
        }

        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
        themeButtons.forEach(button => {
          button.disabled = false;
        });
      }
    }).catch(error => {
      themeButtons.forEach(button => {
        button.disabled = false;
      });
    });
}

function setTheme(themeColor) {
  var currentTheme = 'blue';
  var themeIds = ['red-theme', 'green-theme', 'yellow-theme', 'purple-theme'];

  themeIds.forEach(function (id) {
    var themeStylesheet = document.getElementById(id);
    if (themeStylesheet && !themeStylesheet.disabled) {
      currentTheme = id.replace('-theme', '');
      themeStylesheet.disabled = true;
    }
  });

  if (themeColor !== "blue") {
    var enableTheme = document.getElementById(themeColor + '-theme');
    enableTheme.disabled = false;
  }

  var images = document.querySelectorAll('img');
  images.forEach(function (img) {
    if (img.src.includes('siteicons/' + currentTheme)) {
      img.src = img.src.replace(currentTheme, themeColor);
    }
  });

  var labels = document.querySelectorAll('.theme-preview');
  labels.forEach(function (label) {
    label.classList.remove('is-selected');
  });

  var targetLabel = document.querySelector(`.theme-preview.${themeColor}`);
  if (targetLabel) {
    targetLabel.classList.add('is-selected');
  }

  document.cookie = `colorTheme=${themeColor}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;

  fetch('endpoints/settings/colortheme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ color: themeColor })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
    });

}

function setDesignTheme(design) {
  var designs = ['glass', 'minimal', 'neo', 'vibrant', 'modern'];
  designs.forEach(function(d) {
    var el = document.getElementById('design-' + d);
    if (el) el.disabled = (d !== design);
  });
  // Sync body class so CSS that depends on body.design-X reacts immediately
  document.body.classList.remove('design-glass','design-minimal','design-neo','design-vibrant','design-modern');
  if (design && designs.indexOf(design) !== -1) {
    document.body.classList.add('design-' + design);
  }
  window.designTheme = design;
  // Update active card UI
  document.querySelectorAll('.design-theme-card').forEach(function(card) {
    card.classList.toggle('is-selected', card.dataset.design === design);
  });
  // Re-init mobile-first runtime (FAB, swipe, etc.) when theme is toggled live.
  // Mobile-first improvements apply to ALL themes — no cleanup needed when
  // switching between themes.
  if (typeof window.__modernThemeInit === 'function') {
    window.__modernThemeInit();
  }
  // Persist to localStorage and server
  localStorage.setItem('wallosDesign', design);
  fetch('endpoints/settings/design_theme.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.csrfToken },
    body: JSON.stringify({ design: design })
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(() => showErrorMessage('Error saving design'));
}

function resetCustomColors() {
  const button = document.getElementById("reset-colors");
  button.disabled = true;

  fetch("endpoints/settings/resettheme.php", {
    method: "POST",
    headers: {
      "X-CSRF-Token": window.csrfToken,
    },
    body: new URLSearchParams({
      action: "reset",
    }),
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);

        const customThemeColors = document.getElementById("custom_theme_colors");
        if (customThemeColors) {
          customThemeColors.remove();
        }

        document.documentElement.style.removeProperty("--main-color");
        document.documentElement.style.removeProperty("--accent-color");
        document.documentElement.style.removeProperty("--hover-color");

        document.getElementById("mainColor").value = "#FFFFFF";
        document.getElementById("accentColor").value = "#FFFFFF";
        document.getElementById("hoverColor").value = "#FFFFFF";
      } else {
        showErrorMessage(data.message || translate("failed_reset_colors"));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("unknown_error"));
    })
    .finally(() => {
      button.disabled = false;
    });
}


function saveCustomColors() {
  const button = document.getElementById("save-colors");
  button.disabled = true;

  const mainColor = document.getElementById("mainColor").value;
  const accentColor = document.getElementById("accentColor").value;
  const hoverColor = document.getElementById("hoverColor").value;

  fetch('endpoints/settings/customtheme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ mainColor: mainColor, accentColor: accentColor, hoverColor: hoverColor })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        document.documentElement.style.setProperty('--main-color', mainColor);
        document.documentElement.style.setProperty('--accent-color', accentColor);
        document.documentElement.style.setProperty('--hover-color', hoverColor);
      } else {
        showErrorMessage(data.message);
      }
      button.disabled = false;
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
      button.disabled = false;
    });

}

function saveCustomCss() {
  const button = document.getElementById("save-css");
  button.disabled = true;

  const customCss = document.getElementById("customCss").value;

  fetch('endpoints/settings/customcss.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ customCss: customCss })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
      button.disabled = false;
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
      button.disabled = false;
    });
}
