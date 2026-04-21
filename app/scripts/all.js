if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('service-worker.js').then(function(registration) {
      registration.update();
    }, function(err) {
      console.log('ServiceWorker registration failed: ', err);
    });
  });
}
