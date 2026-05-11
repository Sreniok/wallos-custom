function loadGraph(container, dataPoints, currency, run) {
    if (run) {
        renderChartWithSpinner(container, function(ctx) {
            new Chart(ctx, {
            type: 'pie',
            data: {
                datasets: [{
                    data: dataPoints.map(point => point.y),
                }],
                labels: dataPoints.map(point => {
                    if (currency) {
                        return `${point.label} (${new Intl.NumberFormat(navigator.language, { style: 'currency', currency }).format(point.y)})`;
                    } else {
                        return `${point.label} (${new Intl.NumberFormat(navigator.language).format(point.y)})`;
                    }
                }),
            },
            options: {
                animation: {
                    animateRotate: true,
                    animateScale: true,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = " ";
                                if (currency) {
                                    label += new Intl.NumberFormat(navigator.language, { style: 'currency', currency }).format(context.raw);
                                } else {
                                    label += new Intl.NumberFormat(navigator.language).format(context.raw);
                                }
                                return label;
                            }
                        }
                    }
                }
            },
            });
        });
    }
}

function loadLineGraph(container, dataPoints, currency, run) {
    if (run) {
        renderChartWithSpinner(container, function(ctx) {
            new Chart(ctx, {
            type: 'line',
            data: {
                datasets: [{
                    label: '',
                    data: dataPoints.map(point => point.y),
                }],
                labels: dataPoints.map(point => {
                    return `${point.label}`;
                }),
            },
            options: {
                animation: {
                    animateRotate: true,
                    animateScale: true,
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value, index, values) {
                                if (currency) {
                                    return new Intl.NumberFormat(navigator.language, { style: 'currency', currency }).format(value);
                                } else {
                                    return new Intl.NumberFormat(navigator.language).format(value);
                                }
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
            });
        });
    }
}

function renderChartWithSpinner(container, render) {
    const canvas = document.getElementById(container);
    if (!canvas) {
        return;
    }

    const draw = () => render(canvas.getContext('2d'));
    const host = canvas.closest('.graph');

    if (typeof withSpinner === 'function' && host) {
        withSpinner(new Promise(resolve => {
            requestAnimationFrame(() => {
                draw();
                resolve();
            });
        }), host);
        return;
    }

    draw();
}

function closeSubMenus() {
    var subMenus = document.querySelectorAll('.filtermenu-submenu-content');
    subMenus.forEach(subMenu => {
        subMenu.classList.remove('is-open');
    });

    document.querySelectorAll('.filter-title[aria-expanded="true"]').forEach(button => {
        button.setAttribute('aria-expanded', 'false');
    });
}

document.addEventListener("DOMContentLoaded", function() {
    var filtermenu = document.querySelector('#filtermenu-button');
    filtermenu.addEventListener('click', function() {
        const content = this.parentElement.querySelector('.filtermenu-content');
        const isOpen = content.classList.toggle('is-open');
        this.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        closeSubMenus();
    });

    document.addEventListener('click', function(e) {
        var filtermenuContent = document.querySelector('.filtermenu-content');
        if (filtermenuContent.classList.contains('is-open')) {
            var subMenus = document.querySelectorAll('.filtermenu-submenu');
            var clickedInsideSubmenu = Array.from(subMenus).some(subMenu => subMenu.contains(e.target) || subMenu === e.target);

            if (!filtermenu.contains(e.target) && !clickedInsideSubmenu) {
                closeSubMenus();
                filtermenuContent.classList.remove('is-open');
                filtermenu.setAttribute('aria-expanded', 'false');
            }
        }
    });

    document.addEventListener('keydown', function(e) {
        const filtermenuContent = document.querySelector('.filtermenu-content');
        if (!filtermenuContent || !filtermenuContent.classList.contains('is-open')) {
            return;
        }

        if (e.key === 'Escape') {
            closeSubMenus();
            filtermenuContent.classList.remove('is-open');
            filtermenu.setAttribute('aria-expanded', 'false');
            filtermenu.focus();
            return;
        }

        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') {
            return;
        }

        const focusable = Array.from(filtermenuContent.querySelectorAll('button:not([disabled])'))
            .filter(button => button.offsetParent !== null);
        if (focusable.length === 0) {
            return;
        }

        e.preventDefault();
        const currentIndex = focusable.indexOf(document.activeElement);
        const nextIndex = e.key === 'ArrowDown'
            ? (currentIndex + 1) % focusable.length
            : (currentIndex <= 0 ? focusable.length - 1 : currentIndex - 1);
        focusable[nextIndex].focus();
    });
});

function toggleSubMenu(subMenu) {
    var subMenuElement = document.getElementById("filter-" + subMenu);
    var trigger = document.querySelector('[aria-controls="filter-' + subMenu + '"]');
    if (subMenuElement.classList.contains("is-open")) {
        closeSubMenus();
    } else {
        closeSubMenus();
        subMenuElement.classList.add("is-open");
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'true');
        }
    }
}

document.querySelectorAll('.filter-item').forEach(function(item) {
  item.addEventListener('click', function(e) {
    if (this.hasAttribute('data-categoryid')) {
        const categoryId = this.getAttribute('data-categoryid');
        const urlParams = new URLSearchParams(window.location.search);
        let newUrl = 'stats.php?';

        if (urlParams.get('category') === categoryId) {
            urlParams.delete('category');
        } else {
            urlParams.set('category', categoryId);
        }

        newUrl += urlParams.toString();
        window.location.href = newUrl;
    } else if (this.hasAttribute('data-memberid')) {
        const memberId = this.getAttribute('data-memberid');
        const urlParams = new URLSearchParams(window.location.search);
        let newUrl = 'stats.php?';

        if (urlParams.get('member') === memberId) {
            urlParams.delete('member');
        } else {
            urlParams.set('member', memberId);
        }

        newUrl += urlParams.toString();
        window.location.href = newUrl;
    } else if (this.hasAttribute('data-paymentid')) {
        const paymentId = this.getAttribute('data-paymentid');
        const urlParams = new URLSearchParams(window.location.search);
        let newUrl = 'stats.php?';

        if (urlParams.get('payment') === paymentId) {
            urlParams.delete('payment');
        } else {
            urlParams.set('payment', paymentId);
        }

        newUrl += urlParams.toString();
        window.location.href = newUrl;
    }
  });
});

function clearFilters() {
    window.location.href = 'stats.php';
}
