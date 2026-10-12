import { createApp } from './app.js';

const gateway = await createApp();
await gateway.start();

console.log('Server started, testing health...');

// Wait for server to be ready
await new Promise(resolve => setTimeout(resolve, 1000));

const health = await fetch('http://127.0.0.1:3000/health');
console.log('Health:', await health.text());

const internalHealth = await fetch('http://127.0.0.1:3000/internal/health', {
  headers: { 'X-Kingsway-Worker-Secret': '2fb2aa811335373db381afba579df1df1fda219e350da9354584f31bbe31389ff4b8dc66e8991bb1d691d24ad9c1624cd3df729e529aca039a5713dbe76fdfba' }
});
console.log('Internal health:', await internalHealth.text());

console.log('Shutting down...');
await gateway.close({ reason: 'test-complete' });
console.log('Done');