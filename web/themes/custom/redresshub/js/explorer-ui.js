/**
 * @file
 * The toolbar and side panel of the explorer on the front page.
 *
 * The map, list and network are Views (explorer_* views); this only adds:
 * - opening and closing the filters and the view menus of the toolbar;
 * - the side panel: a marker popup on the map (Leaflet Views) or a tile in
 *   the list opens its content in the panel next to the result.
 */

(($, Drupal, once) => {
  /**
   * Shows content in the side panel of an explorer view.
   */
  const openPanel = (container, html) => {
    const panel = container.querySelector('.explorer__panel');
    if (!panel) {
      return;
    }
    panel.querySelector('.explorer__panel-body').innerHTML = html;
    panel.hidden = false;
    // The page stays where it is: with the top of the stage out of sight, the
    // panel starts below the header, where the visitor is looking.
    const header = document.querySelector('.site-header');
    const visibleTop = (header ? header.getBoundingClientRect().bottom : 0) + 16;
    // In full screen the stage is all there is.
    const offset = document.fullscreenElement ? 0 : Math.max(0, visibleTop - panel.parentElement.getBoundingClientRect().top);
    panel.style.top = `${offset}px`;
    panel.style.minHeight = `calc(100% - ${offset}px)`;
    panel.querySelector('.explorer__panel-close').focus({ preventScroll: true });
  };

  /**
   * Shows the content of a node (research data) in the side panel.
   */
  const openNode = (container, nid) => {
    fetch(Drupal.url(`explorer/panel/${nid}`))
      .then((response) => (response.ok ? response.text() : Promise.reject(response.status)))
      .then((html) => openPanel(container, html))
      .catch(() => {});
  };

  /**
   * Closes the side panel.
   */
  const closePanel = (panel) => {
    panel.hidden = true;
  };

  /**
   * A full screen button on the map: the whole stage (map, time bar, side
   * panel) fills the screen. Not shown where the browser cannot do it (e.g.
   * on iPhones).
   */
  const addFullscreen = (map, stage) => {
    if (!document.fullscreenEnabled || !stage) {
      return;
    }
    const Control = L.Control.extend({
      options: { position: 'topright' },
      onAdd() {
        const bar = L.DomUtil.create('div', 'leaflet-bar explorer__fullscreen');
        const button = L.DomUtil.create('button', '', bar);
        button.type = 'button';
        const update = () => {
          const on = document.fullscreenElement === stage;
          button.innerHTML = `<span class="icon icon--${on ? 'minimize' : 'maximize'}" aria-hidden="true"></span>`;
          button.setAttribute('aria-label', on ? Drupal.t('Exit full screen') : Drupal.t('Full screen'));
          button.title = button.getAttribute('aria-label');
        };
        L.DomEvent.disableClickPropagation(bar);
        L.DomEvent.on(button, 'click', () => {
          if (document.fullscreenElement === stage) {
            document.exitFullscreen();
          }
          else {
            stage.requestFullscreen().catch(() => {});
          }
        });
        // The new size is only known once the browser has laid it out.
        const changed = () => {
          update();
          window.requestAnimationFrame(() => window.requestAnimationFrame(() => map.invalidateSize()));
        };
        document.addEventListener('fullscreenchange', changed);
        map.on('unload', () => document.removeEventListener('fullscreenchange', changed));
        update();
        return bar;
      },
    });
    map.addControl(new Control());
  };

  // A popup of a map marker opens in the side panel instead.
  $(document).on('leafletMapInit', (event, settings, map, mapId) => {
    const container = document.getElementById(mapId)?.closest('.explorer__view');
    if (!container) {
      return;
    }
    addFullscreen(map, container.querySelector('.explorer__stage'));
    // The attribution without the Leaflet prefix; the map data and tiles
    // themselves must stay credited.
    map.attributionControl?.setPrefix(false);
    map.on('popupopen', (e) => {
      const content = e.popup.getContent();
      map.closePopup(e.popup);
      openPanel(container, typeof content === 'string' ? content : content.outerHTML);
    });
  });

  /**
   * Aligns the cards next to the explorer with the top of the stage (the
   * map, list or network), also when the filter panel opens.
   */
  const alignSidebar = (explorer) => {
    const layout = explorer.closest('.map-layout');
    const stage = explorer.querySelector('.explorer__stage');
    if (!layout || !stage || typeof ResizeObserver === 'undefined') {
      return;
    }
    const align = () => {
      if (stage.isConnected) {
        const offset = stage.getBoundingClientRect().top - layout.getBoundingClientRect().top;
        layout.style.setProperty('--map-layout-stage-top', `${Math.max(0, Math.round(offset))}px`);
      }
    };
    new ResizeObserver(align).observe(explorer);
    align();
  };

  Drupal.behaviors.redresshubExplorer = {
    attach(context) {
      once('redresshub-explorer', '[data-explorer]', context).forEach((explorer) => {
        alignSidebar(explorer);
        const toggle = explorer.querySelector('.explorer__filter-toggle');
        const filters = explorer.querySelector('#explorer-filters');
        // Open or closed stays so while switching views and applying
        // filters (each loads a new page), for this browser tab.
        const remember = (open) => {
          try {
            sessionStorage.setItem('redresshub.explorer.filters', open ? 'open' : 'closed');
          }
          catch (e) {
            // No storage (private mode): the panel starts closed.
          }
        };
        const remembered = () => {
          try {
            return sessionStorage.getItem('redresshub.explorer.filters') === 'open';
          }
          catch (e) {
            return false;
          }
        };
        const setFilters = (open) => {
          if (filters) {
            filters.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
          }
        };
        if (filters) {
          setFilters(remembered());
          toggle.addEventListener('click', () => {
            setFilters(filters.hidden);
            remember(!filters.hidden);
          });
        }
        else {
          toggle.hidden = true;
        }

        // View menus.
        const closeMenus = () => {
          explorer.querySelectorAll('.explorer__menu-list').forEach((list) => { list.hidden = true; });
          explorer.querySelectorAll('.explorer__menu > button').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        };
        explorer.querySelectorAll('.explorer__menu').forEach((menu) => {
          const button = menu.querySelector('button');
          const list = menu.querySelector('.explorer__menu-list');
          button.addEventListener('click', () => {
            const open = list.hidden;
            closeMenus();
            list.hidden = !open;
            button.setAttribute('aria-expanded', String(open));
          });
        });
        document.addEventListener('click', (event) => {
          if (!event.target.closest('.explorer__menu')) {
            closeMenus();
          }
        });

        // Tiles open their content in the panel.
        explorer.addEventListener('click', (event) => {
          const tile = event.target.closest('[data-explorer-tile]');
          if (tile) {
            const template = tile.parentElement.querySelector('.explorer__tile-panel');
            openPanel(tile.closest('.explorer__view'), template ? template.innerHTML : '');
          }
          // Linked research data in the panel (it has no page of its own).
          const link = event.target.closest('[data-explorer-nid]');
          if (link) {
            openNode(link.closest('.explorer__view'), link.dataset.explorerNid);
          }
          if (event.target.closest('.explorer__panel-close')) {
            closePanel(event.target.closest('.explorer__panel'));
          }
        });
        // A node of the network (network_views) opens its content in the
        // panel too.
        explorer.addEventListener('networkviews:nodeclick', (event) => {
          const nid = event.detail.id.replace(/^node:/, '');
          const container = event.target.closest('.explorer__view');
          if (!container || !/^\d+$/.test(nid)) {
            return;
          }
          event.preventDefault();
          openNode(container, nid);
        });
        explorer.addEventListener('keydown', (event) => {
          if (event.key === 'Escape') {
            closeMenus();
            explorer.querySelectorAll('.explorer__panel').forEach(closePanel);
          }
        });
      });
    },
  };
})(jQuery, Drupal, once);

/**
 * Keeps the URL of a filtered explorer short: empty filters are not sent.
 */
((Drupal, once) => {
  Drupal.behaviors.redresshubExplorerForm = {
    attach(context) {
      once('redresshub-explorer-form', '.explorer__form', context).forEach((form) => {
        form.addEventListener('submit', (event) => {
          const reset = event.submitter && event.submitter.getAttribute('data-drupal-selector') === 'edit-reset';
          form.querySelectorAll('input[name], select[name]').forEach((field) => {
            const empty = field.value === '' || field.value === 'All';
            // Reset: drop all filters, keep only the chosen view.
            if (field.type === 'submit' || empty || (reset && field.name !== 'view')) {
              field.disabled = true;
            }
          });
        });
      });
    },
  };
})(Drupal, once);
