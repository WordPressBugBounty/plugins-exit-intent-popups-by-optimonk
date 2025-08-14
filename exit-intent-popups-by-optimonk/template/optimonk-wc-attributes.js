(function (OptiMonk) {
    var adapter = OptiMonk.Visitor.createAdapter();
    adapter.Cart.clear();
    {{set_cart_data}}

    {{set_product_data}}
}(OptiMonk));