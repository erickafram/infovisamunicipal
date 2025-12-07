const CACHE_NAME = 'infovisa-v1.0';
const urlsToCache = [
  '/visamunicipal/',
  '/visamunicipal/assets/css/style.css',
  '/visamunicipal/assets/js/main.js',
  '/visamunicipal/assets/js/mobile-menu.js',
  '/visamunicipal/assets/img/logo.png',
  '/visamunicipal/assets/images/icon-192x192.png',
  '/visamunicipal/assets/images/icon-512x512.png'
];

// Instalação do Service Worker
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('Cache aberto');
        return cache.addAll(urlsToCache);
      })
  );
});

// Interceptação de requisições
self.addEventListener('fetch', function(event) {
  // Não interceptar requisições para APIs externas ou domínios diferentes
  const url = new URL(event.request.url);
  const isExternalAPI = !url.origin.includes('infovisa.gurupi.to.gov.br') && !url.pathname.startsWith('/visamunicipal/');
  
  if (isExternalAPI) {
    // Deixar o navegador fazer a requisição normalmente
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        // Cache hit - retorna a resposta do cache
        if (response) {
          return response;
        }
        // Se não está no cache, busca da rede
        return fetch(event.request).catch(function() {
          // Se falhar, retorna uma resposta de fallback para recursos locais
          if (event.request.destination === 'document') {
            return caches.match('/visamunicipal/');
          }
        });
      }
    )
  );
});

// Atualização do Service Worker
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            console.log('Removendo cache antigo:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
}); 