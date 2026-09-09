{{set_variables}}

(function () {
  var PRODUCT_FIELDS = ['name', 'sku', 'price', 'stock', 'categories', 'tags'];

  var pageData = {{page_data}};

  var hasCampaignsBeforeLoadTriggered = false;

  function setAssocData(assocData, adapter) {
    Object.keys(assocData).forEach((key) => {
      adapter.attr(`wp_${key}`, assocData[key]);
    })
  }

  // The six fields campaign targeting reads, written on every page load rather than only
  // when a campaign carrying a visitor attribute rule reaches the page. The campaign init
  // listeners in insert-code.js do not fire at all on a page no campaign is sent to, so a
  // campaign restricted to product pages used to leave the last viewed product on the
  // visitor everywhere else -- cart, checkout, home, account.
  //
  // Off a product page only the fields we set earlier are cleared. Writing empty strings
  // for a visitor who has never seen a product page would flip `is set` / `is not set`
  // rules, because an empty string still counts as set.
  function setProductData(adapter) {
    var product = (window.WooDataForOM && window.WooDataForOM.product) || null;
    var data = {};

    PRODUCT_FIELDS.forEach((field) => {
      var key = `current_product.${field}`;

      if (product) {
        // undefined turns adapter.attr() into a getter, which would leave a stale value behind
        data[key] = product[field] === undefined ? '' : product[field];
      } else if (adapter.attr(`wp_${key}`) !== undefined) {
        data[key] = '';
      }
    });

    setAssocData(data, adapter);
  }

  // The attributes describing the page being viewed -- title, type, categories, dates,
  // search. Same reason as setProductData(): the campaign init listeners do not fire on a
  // page no campaign is sent to, so these used to keep describing the previous page.
  //
  // Note the difference from setProductData(): that one walks a fixed list of six fields
  // because clearing them is the point, while this one walks the keys PHP actually sent.
  // The attribution attributes (utm_*) are left out of this payload precisely so they are
  // never touched here -- do not "unify" the two into one fixed list.
  //
  // A field is written when it has a value, or when the visitor already carries the key --
  // creating empty keys for a visitor who has none would flip `is set` / `is not set`
  // rules. The check is against '' and not falsiness: is_front_page and
  // search_results_count are legitimately 0.
  function setPageData(adapter) {
    var data = {};

    Object.keys(pageData).forEach((key) => {
      if (pageData[key] !== '' || adapter.attr(`wp_${key}`) !== undefined) {
        data[key] = pageData[key];
      }
    });

    setAssocData(data, adapter);
  }

  function triggerEvent(element, eventName, parameters) {
    let event;
    if (document.createEvent) {
      event = document.createEvent('HTMLEvents');
      event.initEvent(eventName, true, true);
    } else if (document.createEventObject) {
      event = document.createEventObject();
      event.eventType = eventName;
    }

    event.eventName = eventName;
    event.parameters = parameters || {};

    if (element.dispatchEvent) {
      element.dispatchEvent(event);
    } else if (element.fireEvent) {
      element.fireEvent(`on${event.eventType}`, event);
    } else if (element[eventName]) {
      element[eventName]();
    } else if (element[`on${eventName}`]) {
      element[`on${eventName}`]();
    }
  };

  document.querySelector('html').addEventListener('optimonk#campaigns-before-load', () => {

    if (hasCampaignsBeforeLoadTriggered) return;
    hasCampaignsBeforeLoadTriggered = true;

    var adapter = OptiMonkEmbedded.Visitor.createAdapter();

    setProductData(adapter);
    setPageData(adapter);

    var orderData = null;
    {{set_order_data}}

    if (orderData) {
      setAssocData(orderData, adapter);
    }

    console.log('[OM-plugin] visitor attributes updated');

    console.log('[OM-plugin] triggered event: optimonk#wc-attributes-updated');
    triggerEvent(document.querySelector('html'), 'optimonk#wc-attributes-updated');

  });

  if (window.WooDataForOM) {
    console.log('[OM-plugin] varibles inserted (window.WooDataForOM)');
  }
}());
