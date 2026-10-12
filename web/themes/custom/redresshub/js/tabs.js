/**
 * @file
 * Turns a .content-tabs block into tabs.
 *
 * Expected markup, as written in the WYSIWYG editor:
 *
 * <div class="content-tabs">
 *   <section class="content-tabs__panel" id="the-map" data-tab-label="The map">…</section>
 *   <section class="content-tabs__panel" id="the-project" data-tab-label="The project">…</section>
 * </div>
 *
 * Without JavaScript, all panels are shown below each other. The tab follows
 * the URL fragment, so links like /about#guidelines open the right tab.
 */

((Drupal, once) => {
  Drupal.behaviors.redresshubTabs = {
    attach(context) {
      once('redresshub-tabs', '.content-tabs', context).forEach((container) => {
        const panels = Array.from(
          container.querySelectorAll(':scope > .content-tabs__panel'),
        );
        if (panels.length < 2) {
          return;
        }

        const list = document.createElement('div');
        list.className = 'content-tabs__list';
        list.setAttribute('role', 'tablist');

        const tabs = panels.map((panel, index) => {
          if (!panel.id) {
            panel.id = `content-tab-panel-${index}`;
          }
          const tab = document.createElement('button');
          tab.type = 'button';
          tab.className = 'content-tabs__tab';
          tab.id = `${panel.id}-tab`;
          tab.textContent =
            panel.dataset.tabLabel ||
            panel.querySelector('h2')?.textContent ||
            `${index + 1}`;
          tab.setAttribute('role', 'tab');
          tab.setAttribute('aria-controls', panel.id);
          panel.setAttribute('role', 'tabpanel');
          panel.setAttribute('aria-labelledby', tab.id);
          panel.tabIndex = 0;
          list.appendChild(tab);
          return tab;
        });

        const select = (index, focus = false) => {
          tabs.forEach((tab, i) => {
            const selected = i === index;
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            tab.tabIndex = selected ? 0 : -1;
            panels[i].hidden = !selected;
          });
          if (focus) {
            tabs[index].focus();
          }
        };

        tabs.forEach((tab, index) => {
          tab.addEventListener('click', () => {
            select(index);
            window.history.replaceState(null, '', `#${panels[index].id}`);
          });
          tab.addEventListener('keydown', (event) => {
            const keys = {
              ArrowRight: index + 1,
              ArrowLeft: index - 1,
              Home: 0,
              End: tabs.length - 1,
            };
            if (event.key in keys) {
              event.preventDefault();
              select((keys[event.key] + tabs.length) % tabs.length, true);
            }
          });
        });

        // Show the tab that contains the target of the URL fragment.
        const selectFromHash = () => {
          const target = window.location.hash
            ? document.getElementById(
                decodeURIComponent(window.location.hash.slice(1)),
              )
            : null;
          const index = target
            ? panels.findIndex((panel) => panel.contains(target))
            : -1;
          if (index !== -1) {
            select(index);
            if (target !== panels[index]) {
              target.scrollIntoView();
            }
            return true;
          }
          return false;
        };

        container.prepend(list);
        container.classList.add('is-enhanced');
        if (!selectFromHash()) {
          select(0);
        }
        window.addEventListener('hashchange', selectFromHash);
      });
    },
  };
})(Drupal, once);
