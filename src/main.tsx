// Monkey patch toFixed untuk menangkap sumber error
(function() {
  const originalToFixed = Number.prototype.toFixed;
  Number.prototype.toFixed = function(fractionDigits?: number): string {
    try {
      const num = Number(this);
      if (isNaN(num)) {
        const stack = new Error().stack || '';
        console.error('toFixed called on non-number:', this, stack);
        // Tampilkan di halaman
        const div = document.createElement('div');
        div.style.cssText = 'position:fixed; top:0; left:0; right:0; background:red; color:white; z-index:9999; padding:10px; font-family:monospace; white-space:pre-wrap; font-size:12px;';
        div.textContent = `toFixed error: ${this} (${typeof this})\n${stack}`;
        document.body.prepend(div);
        return '0.0';
      }
      return originalToFixed.call(num, fractionDigits);
    } catch (e) {
      console.error('toFixed exception:', this, e);
      return '0.0';
    }
  };
})();

import { createRoot } from 'react-dom/client'
import App from './App.tsx'
import './index.css'

// Tangkap error global
window.onerror = function(msg, src, line, col, err) {
  const div = document.createElement('div');
  div.style.cssText = 'position:fixed; top:40px; left:0; right:0; background:#c00; color:white; z-index:9999; padding:10px; font-family:monospace; white-space:pre-wrap; font-size:12px;';
  div.textContent = `GLOBAL ERROR: ${msg}\nat ${src}:${line}`;
  document.body.prepend(div);
};

window.onunhandledrejection = function(e) {
  const div = document.createElement('div');
  div.style.cssText = 'position:fixed; top:80px; left:0; right:0; background:#f80; color:white; z-index:9999; padding:10px; font-family:monospace; white-space:pre-wrap; font-size:12px;';
  div.textContent = `PROMISE ERROR: ${e.reason}`;
  document.body.prepend(div);
};

const root = document.getElementById("root");
if (root) {
  createRoot(root).render(<App />);
}