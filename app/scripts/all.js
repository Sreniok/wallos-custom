if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    const version = encodeURIComponent(window.appVersion || 'dev');
    navigator.serviceWorker.register(`service-worker.js?v=${version}`).then(function(registration) {
      registration.update();
    }, function(err) {
      console.log('ServiceWorker registration failed: ', err);
    });
  });
}
