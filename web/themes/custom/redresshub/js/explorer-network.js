/**
 * @file
 * The network opens with one keyword at random, so it starts with a readable
 * network instead of all initiatives at once.
 *
 * - The Network button of the toolbar gets a random keyword, unless a keyword
 *   is chosen already (the filters the views share).
 * - Opening the network without a keyword in another way (a bookmark, a
 *   shared link) loads it again with a random keyword.
 * - Choosing "- Any -" or removing the keyword in the network itself changes
 *   the network without loading the page, so the whole network stays.
 *
 * The keywords: drupalSettings.redresshubExplorer.networkKeywords (those of
 * enough initiatives, see redresshub.theme).
 */

((Drupal, drupalSettings, once) => {
  const keywords = drupalSettings.redresshubExplorer?.networkKeywords || [];

  /**
   * Returns the URL with a random keyword, or null when it needs none.
   */
  const withRandomKeyword = (href) => {
    const url = new URL(href, window.location.href);
    if (!keywords.length || url.searchParams.get('view') !== 'network' || url.searchParams.has('keyword')) {
      return null;
    }
    url.searchParams.set('keyword', keywords[Math.floor(Math.random() * keywords.length)]);
    return url;
  };

  // Opened without a keyword: a fresh visit, not back or forward, nor a
  // reload.
  const navigation = performance.getEntriesByType('navigation')[0];
  if (!navigation || navigation.type === 'navigate') {
    const url = withRandomKeyword(window.location.href);
    if (url) {
      window.location.replace(url.toString());
    }
  }

  Drupal.behaviors.redresshubExplorerNetwork = {
    attach(context) {
      once('redresshub-network-keyword', 'a.explorer__bar-button:not(.is-active)', context).forEach((link) => {
        const url = withRandomKeyword(link.getAttribute('href'));
        if (url) {
          link.setAttribute('href', url.pathname + url.search);
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
