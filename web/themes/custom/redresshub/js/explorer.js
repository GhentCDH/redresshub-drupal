/**
 * @file
 * The explorer on the front page: a map, a list and a network of the
 * initiatives and organisations, with filters.
 *
 * All data comes from one request (/explorer/data, in the current
 * language) and is filtered in the browser. The filters and the view are
 * kept in the URL, so links like /?type=12&from=2010 open a filtered map.
 *
 * Views:
 * - map-initiatives, map-organisations: Leaflet map with a dot per place.
 * - list-initiatives, list-organisations: tiles.
 * - network: initiatives linked to their keywords (d3); only the keyword
 *   filter applies there.
 */

((Drupal, once, L, d3) => {
  const VIEWS = ['map-initiatives', 'map-organisations', 'list-initiatives', 'list-organisations', 'network'];
  const FILTERS = ['q', 'type', 'org', 'keyword', 'sector', 'from', 'to'];
  const BELGIUM = [50.6, 4.6];

  /**
   * Returns the year of a Y-m-d date, or null.
   */
  const year = (date) => (date ? parseInt(date.slice(0, 4), 10) : null);

  /**
   * Escapes text for use in HTML.
   */
  const escape = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /**
   * Returns the text of HTML, for searching.
   */
  const plain = (html) => {
    const div = document.createElement('div');
    div.innerHTML = html || '';
    return div.textContent;
  };

  class Explorer {
    constructor(element) {
      this.element = element;
      this.form = element.querySelector('.explorer__filters');
      this.stage = element.querySelector('.explorer__stage');
      this.panel = element.querySelector('.explorer__panel');
      this.state = { view: 'map-initiatives', q: '', type: '', org: '', keyword: '', sector: '', from: '', to: '' };
      this.map = null;
      this.markers = null;
      this.fitted = {};
    }

    async init() {
      try {
        const response = await fetch(this.element.dataset.url, { headers: { Accept: 'application/json' } });
        this.data = await response.json();
      }
      catch (e) {
        this.element.querySelector('.explorer__loading').textContent = Drupal.t('The data could not be loaded.');
        return;
      }
      this.element.querySelector('.explorer__loading').remove();
      this.index();
      this.buildFilters();
      this.readUrl();
      this.bind();
      this.showUpdated();
      this.render();
    }

    /**
     * Builds lookup tables.
     */
    index() {
      const { terms, initiatives, organisations } = this.data;
      this.terms = {};
      Object.entries(terms).forEach(([key, list]) => {
        this.terms[key] = new Map(list.map((term) => [term.id, term.name]));
      });
      this.initiatives = new Map(initiatives.map((item) => [item.id, item]));
      this.organisations = new Map(organisations.map((item) => [item.id, item]));
      // Organisation => its initiatives.
      this.organisationInitiatives = new Map(organisations.map((o) => [o.id, []]));
      initiatives.forEach((initiative) => {
        initiative.search = `${initiative.title} ${plain(initiative.text)}`.toLowerCase();
        initiative.organisations.forEach((id) => this.organisationInitiatives.get(id)?.push(initiative));
      });
      organisations.forEach((organisation) => {
        organisation.search = organisation.title.toLowerCase();
      });
      const years = initiatives.flatMap((i) => [year(i.start), year(i.end)]).filter(Boolean);
      this.minYear = years.length ? Math.min(...years) : 1900;
      this.maxYear = Math.max(new Date().getFullYear(), ...years);
    }

    /**
     * Fills the selects and the period sliders.
     */
    buildFilters() {
      const fill = (name, entries) => {
        const select = this.form.elements[name];
        entries.forEach(([id, label]) => select.add(new Option(label, id)));
      };
      fill('type', [...this.terms.types]);
      fill('keyword', [...this.terms.keywords]);
      fill('sector', [...this.terms.sectors]);
      fill('org', [...this.organisations.values()].sort((a, b) => a.title.localeCompare(b.title)).map((o) => [o.id, o.title]));
      ['from', 'to'].forEach((name) => {
        const input = this.form.elements[name];
        input.min = this.minYear;
        input.max = this.maxYear;
        input.step = 1;
      });
    }

    /**
     * Reads the view and filters from the URL. Terms may be given by ID or
     * by name, e.g. ?sector=heritage management.
     */
    readUrl() {
      const params = new URLSearchParams(window.location.search);
      const byName = (key, value) => {
        if (/^\d+$/.test(value)) {
          return value;
        }
        const wanted = value.toLowerCase();
        if (key === 'org') {
          const match = [...this.organisations.values()].find((o) => o.title.toLowerCase() === wanted);
          return match ? String(match.id) : '';
        }
        // Terms match on their name in the current language or in English,
        // so links like ?type=restitution work in every language.
        const list = this.data.terms[{ type: 'types', keyword: 'keywords', sector: 'sectors' }[key]];
        const match = list.find((term) => [term.name, term.original].some((name) => name?.toLowerCase() === wanted));
        return match ? String(match.id) : '';
      };
      if (VIEWS.includes(params.get('view'))) {
        this.state.view = params.get('view');
      }
      FILTERS.forEach((key) => {
        const value = params.get(key);
        if (value) {
          this.state[key] = ['type', 'org', 'keyword', 'sector'].includes(key) ? byName(key, value) : value;
        }
      });
      this.writeForm();
    }

    writeUrl() {
      const params = new URLSearchParams();
      if (this.state.view !== 'map-initiatives') {
        params.set('view', this.state.view);
      }
      FILTERS.forEach((key) => {
        if (this.state[key]) {
          params.set(key, this.state[key]);
        }
      });
      const query = params.toString();
      window.history.replaceState(null, '', query ? `?${query}` : window.location.pathname);
    }

    writeForm() {
      ['q', 'type', 'org', 'keyword', 'sector'].forEach((key) => {
        this.form.elements[key].value = this.state[key];
      });
      this.form.elements.from.value = this.state.from || this.minYear;
      this.form.elements.to.value = this.state.to || this.maxYear;
      this.updatePeriodLabel();
    }

    readForm() {
      ['q', 'type', 'org', 'keyword', 'sector'].forEach((key) => {
        this.state[key] = this.form.elements[key].value.trim();
      });
      let from = parseInt(this.form.elements.from.value, 10);
      let to = parseInt(this.form.elements.to.value, 10);
      if (from > to) {
        [from, to] = [to, from];
      }
      // The full range means: no period filter.
      this.state.from = from > this.minYear ? String(from) : '';
      this.state.to = to < this.maxYear ? String(to) : '';
    }

    updatePeriodLabel() {
      const from = this.form.elements.from.value;
      const to = this.form.elements.to.value;
      this.element.querySelector('.explorer__period-value').textContent = `${Math.min(from, to)} – ${Math.max(from, to)}`;
    }

    bind() {
      const toggle = this.element.querySelector('.explorer__filter-toggle');
      toggle.addEventListener('click', () => this.toggleFilters(this.form.hidden));

      // Dropdown menus of the views.
      this.element.querySelectorAll('.explorer__menu').forEach((menu) => {
        const button = menu.querySelector('button');
        const list = menu.querySelector('.explorer__menu-list');
        button.addEventListener('click', () => {
          const open = list.hidden;
          this.closeMenus();
          list.hidden = !open;
          button.setAttribute('aria-expanded', String(open));
        });
      });
      document.addEventListener('click', (event) => {
        if (!event.target.closest('.explorer__menu')) {
          this.closeMenus();
        }
      });
      this.element.querySelectorAll('[data-view]').forEach((button) => {
        button.addEventListener('click', () => {
          this.closeMenus();
          this.state.view = button.dataset.view;
          this.render();
        });
      });

      this.form.addEventListener('input', (event) => {
        if (event.target.type === 'range') {
          this.updatePeriodLabel();
        }
      });
      this.form.addEventListener('change', () => {
        this.readForm();
        this.render();
      });
      this.form.addEventListener('submit', (event) => {
        event.preventDefault();
        this.readForm();
        this.render();
        this.toggleFilters(false);
      });
      this.form.addEventListener('reset', (event) => {
        event.preventDefault();
        FILTERS.forEach((key) => { this.state[key] = ''; });
        this.writeForm();
        this.render();
      });

      this.panel.querySelector('.explorer__panel-close').addEventListener('click', () => this.closePanel());
      this.panel.addEventListener('click', (event) => {
        const link = event.target.closest('[data-show]');
        if (link) {
          event.preventDefault();
          const [kind, id] = link.dataset.show.split(':');
          this.showItem(kind, parseInt(id, 10));
        }
      });
      this.element.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          this.closeMenus();
          this.closePanel();
        }
      });
    }

    toggleFilters(open) {
      this.form.hidden = !open;
      this.element.querySelector('.explorer__filter-toggle').setAttribute('aria-expanded', String(open));
    }

    closeMenus() {
      this.element.querySelectorAll('.explorer__menu-list').forEach((list) => { list.hidden = true; });
      this.element.querySelectorAll('.explorer__menu > button').forEach((button) => button.setAttribute('aria-expanded', 'false'));
    }

    showUpdated() {
      if (!this.data.updated) {
        return;
      }
      const note = this.element.querySelector('.explorer__updated');
      const date = new Date(`${this.data.updated}T12:00:00`).toLocaleDateString(document.documentElement.lang || undefined, { year: 'numeric', month: 'long', day: 'numeric' });
      note.textContent = Drupal.t('Map data last updated @date.', { '@date': date });
      note.hidden = false;
    }

    /**
     * Filtering.
     */
    periodMatches(start, end) {
      if (!this.state.from && !this.state.to) {
        return true;
      }
      const s = year(start);
      if (!s) {
        return false;
      }
      const e = year(end) ?? this.maxYear;
      return (!this.state.to || s <= parseInt(this.state.to, 10)) && (!this.state.from || e >= parseInt(this.state.from, 10));
    }

    initiativeMatches(initiative, { keywordOnly = false } = {}) {
      const s = this.state;
      if (s.keyword && !initiative.keywords.includes(+s.keyword)) {
        return false;
      }
      if (keywordOnly) {
        return true;
      }
      if (s.q && !initiative.search.includes(s.q.toLowerCase())) {
        return false;
      }
      if (s.type && !initiative.types.includes(+s.type)) {
        return false;
      }
      if (s.org && !initiative.organisations.includes(+s.org)) {
        return false;
      }
      if (s.sector && !initiative.organisations.some((id) => this.organisations.get(id)?.sectors.includes(+s.sector))) {
        return false;
      }
      return this.periodMatches(initiative.start, initiative.end);
    }

    organisationMatches(organisation) {
      const s = this.state;
      if (s.q && !organisation.search.includes(s.q.toLowerCase())) {
        return false;
      }
      if (s.org && organisation.id !== +s.org) {
        return false;
      }
      if (s.sector && !organisation.sectors.includes(+s.sector)) {
        return false;
      }
      const initiatives = this.organisationInitiatives.get(organisation.id) || [];
      if (s.type && !initiatives.some((i) => i.types.includes(+s.type))) {
        return false;
      }
      if (s.keyword && !initiatives.some((i) => i.keywords.includes(+s.keyword))) {
        return false;
      }
      if (s.from || s.to) {
        return organisation.start
          ? this.periodMatches(organisation.start, organisation.end)
          : initiatives.some((i) => this.periodMatches(i.start, i.end));
      }
      return true;
    }

    filtered(kind) {
      return kind === 'organisations'
        ? [...this.organisations.values()].filter((o) => this.organisationMatches(o))
        : [...this.initiatives.values()].filter((i) => this.initiativeMatches(i));
    }

    /**
     * Rendering.
     */
    render() {
      const { view } = this.state;
      const kind = view.endsWith('organisations') ? 'organisations' : 'initiatives';
      const isNetwork = view === 'network';

      this.element.dataset.view = view;
      this.element.querySelectorAll('[data-view]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.view === view);
      });
      this.element.querySelectorAll('.explorer__menu').forEach((menu) => {
        menu.classList.toggle('is-active', !!menu.querySelector(`[data-view="${view}"]`));
      });
      // In the network only the keyword filter applies.
      this.form.querySelectorAll('[data-filter]').forEach((field) => {
        const disabled = isNetwork && field.dataset.filter !== 'keyword';
        field.classList.toggle('is-disabled', disabled);
        field.querySelectorAll('input, select').forEach((input) => { input.disabled = disabled; });
      });
      this.form.querySelector('.explorer__network-note').hidden = !isNetwork;

      const active = isNetwork
        ? (this.state.keyword ? 1 : 0)
        : FILTERS.filter((key) => key !== 'to' && (this.state[key] || (key === 'from' && this.state.to))).length;
      const count = this.element.querySelector('.explorer__count');
      count.textContent = active;
      count.hidden = !active;

      this.stage.querySelector('.explorer__map').hidden = !view.startsWith('map');
      this.stage.querySelector('.explorer__list').hidden = !view.startsWith('list');
      this.stage.querySelector('.explorer__network').hidden = !isNetwork;
      this.closePanel();

      if (view.startsWith('map')) {
        this.renderMap(kind);
      }
      else if (view.startsWith('list')) {
        this.renderList(kind);
      }
      else {
        this.renderNetwork();
      }
      this.writeUrl();
    }

    renderMap(kind) {
      const container = this.stage.querySelector('.explorer__map');
      if (!this.map) {
        this.map = L.map(container, { center: BELGIUM, zoom: 8, scrollWheelZoom: false });
        L.tileLayer(this.element.dataset.tiles, { maxZoom: 19, attribution: this.element.dataset.attribution }).addTo(this.map);
        this.markers = L.layerGroup().addTo(this.map);
        // Scroll zoom only after a click in the map, so the page scrolls.
        this.map.on('click focus', () => this.map.scrollWheelZoom.enable());
        container.addEventListener('mouseleave', () => this.map.scrollWheelZoom.disable());
      }
      else {
        this.map.invalidateSize();
      }
      this.markers.clearLayers();

      // One dot per place, with all items at that place.
      const places = new Map();
      this.filtered(kind).forEach((item) => {
        item.places.forEach((place) => {
          const key = `${place.lat},${place.lng}`;
          if (!places.has(key)) {
            places.set(key, { ...place, items: [] });
          }
          if (!places.get(key).items.includes(item)) {
            places.get(key).items.push(item);
          }
        });
      });

      const bounds = [];
      places.forEach((place) => {
        const count = place.items.length;
        const marker = L.circleMarker([place.lat, place.lng], {
          radius: count > 1 ? 9 : 7,
          color: '#ffffff',
          weight: 2,
          fillColor: kind === 'organisations' ? '#3a3633' : '#914853',
          fillOpacity: 0.9,
        });
        const names = place.items.map((item) => item.title).join(', ');
        marker.bindTooltip(count > 1 ? `${escape(place.name)} (${count})` : escape(names), { direction: 'top', offset: [0, -6] });
        marker.on('click', () => this.showPlace(kind, place));
        marker.addTo(this.markers);
        bounds.push([place.lat, place.lng]);
      });

      this.setStatus(this.mapStatus(kind, places.size));
      if (bounds.length && !this.fitted[kind]) {
        this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 9 });
        this.fitted[kind] = true;
      }
    }

    mapStatus(kind, count) {
      const total = this.filtered(kind).length;
      const without = this.filtered(kind).filter((item) => !item.places.length).length;
      let text = kind === 'organisations'
        ? Drupal.formatPlural(total, '1 organisation', '@count organisations')
        : Drupal.formatPlural(total, '1 initiative', '@count initiatives');
      text += `, ${Drupal.formatPlural(count, '1 place', '@count places')}`;
      if (without) {
        text += ` (${Drupal.formatPlural(without, '1 without a location', '@count without a location')})`;
      }
      return text;
    }

    setStatus(text) {
      let status = this.stage.querySelector('.explorer__status');
      if (!status) {
        status = document.createElement('p');
        status.className = 'explorer__status';
        status.setAttribute('aria-live', 'polite');
        this.stage.append(status);
      }
      status.textContent = text;
    }

    renderList(kind) {
      const list = this.stage.querySelector('.explorer__list');
      const items = this.filtered(kind).sort((a, b) => a.title.localeCompare(b.title));
      const meta = (item) => {
        if (kind === 'organisations') {
          return item.sectors.map((id) => this.terms.sectors.get(id)).filter(Boolean).join(', ');
        }
        const from = year(item.start);
        const to = year(item.end);
        const period = from ? (to && to !== from ? `${from}–${to}` : (item.end ? `${from}` : `${from}–`)) : '';
        return [item.types.map((id) => this.terms.types.get(id)).filter(Boolean).join(', '), period].filter(Boolean).join(' · ');
      };
      list.innerHTML = `
        <p class="explorer__list-count">${escape(kind === 'organisations'
          ? Drupal.formatPlural(items.length, '1 organisation', '@count organisations')
          : Drupal.formatPlural(items.length, '1 initiative', '@count initiatives'))}</p>
        <ul class="explorer__tiles">
          ${items.map((item) => `
            <li><button type="button" class="explorer__tile" data-show="${kind}:${item.id}">
              <span class="explorer__tile-title">${escape(item.title)}</span>
              <span class="explorer__tile-meta">${escape(meta(item))}</span>
            </button></li>`).join('')}
        </ul>`;
      list.querySelectorAll('[data-show]').forEach((tile) => {
        tile.addEventListener('click', () => {
          const [k, id] = tile.dataset.show.split(':');
          this.showItem(k, parseInt(id, 10));
        });
      });
      this.setStatus('');
    }

    /**
     * Network: initiatives linked to their keywords.
     */
    renderNetwork() {
      const container = this.stage.querySelector('.explorer__network');
      container.innerHTML = '';
      const width = container.clientWidth || 800;
      const height = container.clientHeight || 560;

      const initiatives = [...this.initiatives.values()].filter((i) => i.keywords.length && this.initiativeMatches(i, { keywordOnly: true }));
      const keywordIds = new Set(initiatives.flatMap((i) => i.keywords));
      const nodes = [
        ...[...keywordIds].map((id) => ({ id: `k${id}`, kind: 'keyword', termId: id, label: this.terms.keywords.get(id) || '', degree: 0 })),
        ...initiatives.map((i) => ({ id: `i${i.id}`, kind: 'initiative', item: i, label: i.title })),
      ];
      const byId = new Map(nodes.map((n) => [n.id, n]));
      const links = initiatives.flatMap((i) => i.keywords.map((k) => {
        byId.get(`k${k}`).degree += 1;
        return { source: `i${i.id}`, target: `k${k}` };
      }));

      if (!nodes.length) {
        this.setStatus(Drupal.t('No initiatives with keywords.'));
        return;
      }

      const svg = d3.select(container).append('svg')
        .attr('viewBox', [0, 0, width, height])
        .attr('role', 'img')
        .attr('aria-label', Drupal.t('Network of initiatives and their keywords'));
      const zoomLayer = svg.append('g');
      const zoom = d3.zoom().scaleExtent([0.3, 4]).on('zoom', (event) => zoomLayer.attr('transform', event.transform));
      svg.call(zoom);

      const link = zoomLayer.append('g').attr('class', 'network__links')
        .selectAll('line').data(links).join('line');
      const node = zoomLayer.append('g').attr('class', 'network__nodes')
        .selectAll('g').data(nodes).join('g')
        .attr('class', (d) => `network__node network__node--${d.kind}${d.kind === 'keyword' && String(d.termId) === this.state.keyword ? ' is-selected' : ''}`)
        .attr('tabindex', 0)
        .attr('role', 'button')
        .attr('aria-label', (d) => d.label);
      node.append('circle').attr('r', (d) => (d.kind === 'keyword' ? 6 + Math.sqrt(d.degree) * 3 : 5));
      node.filter((d) => d.kind === 'keyword').append('text')
        .attr('dy', (d) => -(10 + Math.sqrt(d.degree) * 3))
        .text((d) => d.label);
      node.append('title').text((d) => d.label);

      const activate = (event, d) => {
        if (d.kind === 'initiative') {
          this.showItem('initiatives', d.item.id);
        }
        else {
          // A keyword: filter on it (or clear it when it is selected).
          this.state.keyword = this.state.keyword === String(d.termId) ? '' : String(d.termId);
          this.writeForm();
          this.render();
        }
      };
      node.on('click', activate).on('keydown', (event, d) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          activate(event, d);
        }
      });
      node.call(d3.drag()
        .on('start', (event, d) => {
          if (!event.active) simulation.alphaTarget(0.3).restart();
          d.fx = d.x;
          d.fy = d.y;
        })
        .on('drag', (event, d) => {
          d.fx = event.x;
          d.fy = event.y;
        })
        .on('end', (event, d) => {
          if (!event.active) simulation.alphaTarget(0);
          d.fx = null;
          d.fy = null;
        }));

      const simulation = d3.forceSimulation(nodes)
        .force('link', d3.forceLink(links).id((d) => d.id).distance(45).strength(0.6))
        .force('charge', d3.forceManyBody().strength((d) => (d.kind === 'keyword' ? -220 : -40)))
        .force('center', d3.forceCenter(width / 2, height / 2))
        .force('collide', d3.forceCollide().radius((d) => (d.kind === 'keyword' ? 14 : 7)))
        // Pull loose parts towards the middle, so the network stays compact.
        .force('x', d3.forceX(width / 2).strength(0.06))
        .force('y', d3.forceY(height / 2).strength(0.08))
        .on('end', () => {
          // Zoom so the whole network fits.
          const xs = nodes.map((n) => n.x);
          const ys = nodes.map((n) => n.y);
          const [x0, x1, y0, y1] = [Math.min(...xs), Math.max(...xs), Math.min(...ys), Math.max(...ys)];
          const scale = Math.min(2, 0.9 / Math.max((x1 - x0) / width, (y1 - y0) / height));
          const transform = d3.zoomIdentity.translate(width / 2, height / 2).scale(scale).translate(-(x0 + x1) / 2, -(y0 + y1) / 2);
          svg.transition().duration(500).call(zoom.transform, transform);
        })
        .on('tick', () => {
          link.attr('x1', (d) => d.source.x).attr('y1', (d) => d.source.y).attr('x2', (d) => d.target.x).attr('y2', (d) => d.target.y);
          node.attr('transform', (d) => `translate(${d.x},${d.y})`);
        });

      this.setStatus(`${Drupal.formatPlural(initiatives.length, '1 initiative', '@count initiatives')}, ${Drupal.formatPlural(keywordIds.size, '1 keyword', '@count keywords')}. ${Drupal.t('Click a keyword to filter on it.')}`);
    }

    /**
     * Side panel.
     */
    showPlace(kind, place) {
      if (place.items.length === 1) {
        this.showItem(kind, place.items[0].id);
        return;
      }
      this.openPanel(`
        <p class="explorer__panel-kicker">${escape(place.name)}</p>
        <ul class="explorer__panel-list">
          ${place.items.map((item) => `<li><a href="${escape(item.url)}" data-show="${kind}:${item.id}">${escape(item.title)}</a></li>`).join('')}
        </ul>`);
    }

    showItem(kind, id) {
      const item = (kind === 'organisations' ? this.organisations : this.initiatives).get(id);
      if (!item) {
        return;
      }
      const tags = (ids, key) => ids.map((tid) => this.terms[key].get(tid)).filter(Boolean)
        .map((name) => `<li>${escape(name)}</li>`).join('');
      const period = (item.start || item.end)
        ? [year(item.start), item.end ? year(item.end) : Drupal.t('ongoing')].filter(Boolean)
          .filter((value, index, all) => all.indexOf(value) === index).join(' – ')
        : '';
      const rows = [];
      const row = (label, html) => {
        if (html) {
          rows.push(`<dt>${escape(label)}</dt><dd>${html}</dd>`);
        }
      };

      if (kind === 'organisations') {
        const initiatives = this.organisationInitiatives.get(item.id) || [];
        row(Drupal.t('Sector'), tags(item.sectors, 'sectors') && `<ul class="explorer__tags">${tags(item.sectors, 'sectors')}</ul>`);
        row(Drupal.t('Status'), tags(item.status, 'status') && `<ul class="explorer__tags">${tags(item.status, 'status')}</ul>`);
        row(Drupal.t('Period'), escape(period));
        row(Drupal.t('Location'), escape(item.places.map((p) => p.name).join(', ')));
        row(Drupal.t('Initiatives'), initiatives.length && `<ul class="explorer__links">${initiatives.map((i) => `<li><a href="${escape(i.url)}" data-show="initiatives:${i.id}">${escape(i.title)}</a></li>`).join('')}</ul>`);
        row(Drupal.t('Website'), item.website && `<a href="${escape(item.website)}" rel="noopener" target="_blank">${escape(item.website.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, ''))}</a>`);
      }
      else {
        const organisations = item.organisations.map((oid) => this.organisations.get(oid)).filter(Boolean);
        row(Drupal.t('Period'), escape(period) + (period && !item.certain ? ` <span class="explorer__uncertain">(${escape(Drupal.t('uncertain'))})</span>` : ''));
        row(Drupal.t('Type of initiative'), tags(item.types, 'types') && `<ul class="explorer__tags">${tags(item.types, 'types')}</ul>`);
        row(Drupal.t('UN category'), tags(item.un, 'un') && `<ul class="explorer__tags">${tags(item.un, 'un')}</ul>`);
        row(Drupal.t('Organisation(s)'), organisations.length && `<ul class="explorer__links">${organisations.map((o) => `<li><a href="${escape(o.url)}" data-show="organisations:${o.id}">${escape(o.title)}</a></li>`).join('')}</ul>`);
        row(Drupal.t('Location'), escape(item.places.map((p) => p.name).join(', ')));
        row(Drupal.t('Keywords'), tags(item.keywords, 'keywords') && `<ul class="explorer__tags">${tags(item.keywords, 'keywords')}</ul>`);
      }

      const resources = (item.resources || []).map((url) => `<li><a href="${escape(url)}" rel="noopener" target="_blank">${escape(url.replace(/^https?:\/\/(www\.)?/, '').split('/')[0])}</a></li>`).join('');
      this.openPanel(`
        <p class="explorer__panel-kicker">${escape(kind === 'organisations' ? Drupal.t('Organisation') : Drupal.t('Initiative'))}</p>
        <h3 class="explorer__panel-title">${escape(item.title)}</h3>
        <dl class="explorer__facts">${rows.join('')}</dl>
        ${item.text ? `<div class="explorer__panel-text">${item.text}</div>` : ''}
        ${resources ? `<h4>${escape(Drupal.t('Resources'))}</h4><ul class="explorer__links">${resources}</ul>` : ''}
        <p><a class="button button--small" href="${escape(item.url)}">${escape(Drupal.t('Open page'))}</a></p>`);
    }

    openPanel(html) {
      this.panel.querySelector('.explorer__panel-body').innerHTML = html;
      this.panel.hidden = false;
      this.panel.scrollTop = 0;
      this.panel.querySelector('.explorer__panel-close').focus({ preventScroll: true });
    }

    closePanel() {
      this.panel.hidden = true;
    }
  }

  Drupal.behaviors.redresshubExplorer = {
    attach(context) {
      once('redresshub-explorer', '[data-explorer]', context).forEach((element) => {
        new Explorer(element).init();
      });
    },
  };
})(Drupal, once, window.L, window.d3);
