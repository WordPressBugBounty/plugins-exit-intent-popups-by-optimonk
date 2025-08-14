(function() {
  var pvData = {{basic_data}};
  var body = {
    url: encodeURIComponent(window.location.href),
    referrer: encodeURIComponent(document.referrer)
  };

  function makePostRequest(e,t){var n=[];if(t.body){for(var o in t.body)n.push(encodeURIComponent(o)+"="+encodeURIComponent(t.body[o]));t.body=n.join("&")}return window.fetch?new Promise(n=>{var o={method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},redirect:"follow",body:{},...t};window.fetch(e,o).then(e=>{n(e.text())})}):new Promise(t=>{var o;"undefined"==typeof XMLHttpRequest&&t(null),(o=new XMLHttpRequest).open("POST",e,!0),o.onreadystatechange=function(){4==o.readyState&&200==o.status&&t(o.responseText)},o.setRequestHeader("Content-type","application/x-www-form-urlencoded"),o.send(n.join("&"))})}
  function observeAjaxCartActions(e){new PerformanceObserver(function(t){for(var n=t.getEntries(),r=0;r<n.length;r++)["xmlhttprequest","fetch"].includes(n[r].initiatorType)&&['=get_refreshed_fragments', 'add_to_cart'].some(p => n[r].name.includes(p))&&fetchCart(e)}).observe({entryTypes:["resource"]})};

  function fetchCart(adapter) {
    makePostRequest("{{siteUrl}}/index.php?plugin=optimonk&action=cartData", { body })
      .then((response) => {
        var data = JSON.parse(response);
        setCartData(data, adapter);
      })
  }

  function setCartData(cartData, adapter) {
    Object.keys(cartData.avs).forEach((key, value) => {
      adapter.attr(`wp_${key}`, cartData.avs[key]);
    })
  }

  function setAssocData(assocData, adapter) {
    Object.keys(assocData).forEach((key) => {
      adapter.attr(`wp_${key}`, assocData[key]);
    })
  }

  var RuleHandler = {
    hasVisitorCartRules: (campaignMeta) => {
      return !!(campaignMeta.rules.visitorCart || campaignMeta.rules.visitorCartRevamp || campaignMeta.rules.visitorCartV3);
    },
    hasVisitorAttributeRule: (campaignMeta) => {
      return !!campaignMeta.rules.visitorAttribute;
    }
  }

  document.querySelector('html').addEventListener('optimonk#embedded-campaigns-init', function(e) {
    var campaignsData = e.parameters;

    if (!campaignsData.length) {
      return;
    }

    var adapter = window.OptiMonkEmbedded.Visitor.createAdapter();
    var hasCartRule = false;
    var hasAttributeRule = false;

    campaignsData.forEach((campaignMetaData) => {
      hasCartRule = hasCartRule || RuleHandler.hasVisitorCartRules(campaignMetaData);
      hasAttributeRule = hasAttributeRule || RuleHandler.hasVisitorAttributeRule(campaignMetaData);
    });

    setAssocData(pvData, adapter);

    if (hasCartRule) {
      observeAjaxCartActions(adapter);
      fetchCart(adapter);
    }

    if (hasAttributeRule) {
      makePostRequest("{{siteUrl}}/index.php?plugin=optimonk&action=productData", { body })
        .then((response) => {
          var data = JSON.parse(response);
          setAssocData(data, adapter);
        })
    }
  });

  document.querySelector('html').addEventListener('optimonk#campaigns_initialized', function () {
    if (!OptiMonk.campaigns) {
      return;
    }

    var adapter = window.OptiMonk.Visitor.createAdapter();

    setAssocData(pvData, adapter);

    if (OptiMonk.campaigns.filter(campaign => campaign.hasVisitorAttributeRules()).length) {
      makePostRequest("{{siteUrl}}/index.php?plugin=optimonk&action=productData", { body })
        .then((response) => {
          var data = JSON.parse(response);
          setAssocData(data, adapter);
        });
    }
  });
}());
