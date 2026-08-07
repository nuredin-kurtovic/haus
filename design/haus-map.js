/* <haus-map data-cities='[{"ime","lat","lon","status"}]'> — Leaflet + OSM map of HAUS cities. */
(function () {
  if (window.customElements && customElements.get('haus-map')) return;

  const CSS = `
    haus-map .leaflet-container{font-family:Poppins,system-ui,sans-serif;background:#FFFCF2}
    haus-map .leaflet-bar,haus-map .leaflet-bar a,haus-map .leaflet-control-attribution{border-radius:0 !important}
    haus-map .leaflet-bar{border:1px solid #252422;box-shadow:none}
    haus-map .leaflet-bar a{color:#252422;border-bottom:1px solid #CCC5B9;font-weight:600}
    haus-map .leaflet-bar a:hover{background:#FFFCF2;color:#252422}
    haus-map .leaflet-control-attribution{background:rgba(255,252,242,.92);color:#403D39;font-size:11px;padding:3px 7px}
    haus-map .leaflet-control-attribution a{color:#252422}
    haus-map .haus-pin{background:none;border:0}
  `;

  const wait = (test) => new Promise((res) => {
    if (test()) return res();
    const t = setInterval(() => { if (test()) { clearInterval(t); res(); } }, 40);
  });

  class HausMap extends HTMLElement {
    static get observedAttributes() { return ['data-cities']; }

    connectedCallback() { this.boot(); }

    attributeChangedCallback() { if (this._layer) this.draw(); }

    async boot() {
      if (this._booted) return;
      this._booted = true;
      this.style.display = 'block';
      this.style.position = 'relative';

      const style = document.createElement('style');
      style.textContent = CSS;
      this.appendChild(style);

      const box = document.createElement('div');
      box.style.cssText = 'position:absolute;inset:0;background:#FFFCF2';
      this.appendChild(box);

      await wait(() => window.L && window.L.map);

      this._map = L.map(box, { scrollWheelZoom: false, zoomSnap: 0.25, minZoom: 6 });
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18
      }).addTo(this._map);
      this._layer = L.layerGroup().addTo(this._map);
      this._map.setView([44.1, 17.7], 7);
      this.draw();
      setTimeout(() => this._map && this._map.invalidateSize(), 60);
    }

    cities() {
      try { return JSON.parse(this.getAttribute('data-cities') || '[]'); } catch (e) { return []; }
    }

    draw() {
      const list = this.cities().filter(c => typeof c.lat === 'number' && typeof c.lon === 'number');
      this._layer.clearLayers();
      if (!list.length) return;

      list.forEach((c) => {
        const live = c.status !== 'U pripremi';
        const fill = live ? '#FE5100' : '#FFFCF2';
        const html =
          '<div style="position:relative;width:20px;height:20px">' +
            '<span style="position:absolute;inset:0;background:' + fill + ';border:2px solid #252422;display:block"></span>' +
            '<span style="position:absolute;left:27px;top:1px;background:#252422;color:#FFFCF2;' +
              'font:600 12px/1.35 Poppins,system-ui,sans-serif;padding:4px 8px;white-space:nowrap">' +
              String(c.ime || '').replace(/[<>&]/g, '') +
              (live ? '' : ' · u pripremi') +
            '</span>' +
          '</div>';
        L.marker([c.lat, c.lon], {
          icon: L.divIcon({ className: 'haus-pin', html: html, iconSize: [20, 20], iconAnchor: [10, 10] }),
          keyboard: false,
          title: c.ime
        }).addTo(this._layer);
      });

      const b = L.latLngBounds(list.map(c => [c.lat, c.lon]));
      this._map.fitBounds(b, { padding: [70, 90], maxZoom: 9 });
    }
  }

  customElements.define('haus-map', HausMap);
})();
