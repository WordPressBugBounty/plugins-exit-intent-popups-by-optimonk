{{set_variables}}

let hasCampaignsBeforeLoadTriggered = false;

document.querySelector('html').addEventListener('optimonk#campaigns-before-load', () => {

    if (hasCampaignsBeforeLoadTriggered) return;
    hasCampaignsBeforeLoadTriggered = true;

    function setAssocData(assocData, adapter) {
        Object.keys(assocData).forEach((key) => {
        adapter.attr(`wp_${key}`, assocData[key]);
        })
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
    
    var adapter = OptiMonkEmbedded.Visitor.createAdapter();

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
