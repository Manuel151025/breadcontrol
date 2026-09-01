// assets/js/portal_nuevo_pedido.js — logica del carrito de nuevo pedido del portal.
// Requiere: utils.js (que define appUrl desde <body data-app-url>).
//
// Los datos que antes llegaban en un bloque <script> incrustado ahora vienen de
// un <script type="application/json">, que el navegador no ejecuta. Ese cambio
// es lo que permitira quitar 'unsafe-inline' de la CSP (punto 22 del anexo).

var __datosPedido = (function () {
    var el = document.getElementById('datos-pedido');
    try { return el ? JSON.parse(el.textContent) : {}; } catch (e) { return {}; }
}());

// Datos que vienen del servidor.
var cart         = __datosPedido.cart || [];
var bonifPreload = __datosPedido.bonifPreload || [];
var esAprendiz   = !!__datosPedido.esAprendiz;
var esTienda     = !!__datosPedido.esTienda;

// Estado propio del navegador: no lo aporta PHP, se inicializa aqui.
var catalogVars   = [];
var currentPrice  = 0;
var currentCatId  = 0;
var bonifCredito  = 0;
var bonifLoaded   = false;
var allVarieties  = [];

    function setPedidoPara(val) {
        document.getElementById('pedido_para_input').value = val;
        document.getElementById('btn-adso').classList.toggle('active', val === 'adso');
        document.getElementById('btn-personal').classList.toggle('active', val === 'personal');
        var hint = document.getElementById('ap-hint');
        var dtSection = document.getElementById('delivery-datetime-section');
        var inpFecha = document.getElementById('inp_fecha_entrega');
        var inpHora = document.getElementById('inp_hora_entrega');

        if (val === 'adso') {
            hint.textContent = 'Tu pedido se cargará a la cuenta del instructor ADSO.';
            hint.style.color = '#1565c0';
            esTienda = esAprendiz ? false : true;
            if (dtSection) dtSection.style.display = 'none';
            if (inpFecha) inpFecha.removeAttribute('required');
            if (inpHora) inpHora.removeAttribute('required');
        } else {
            hint.textContent = 'Tu pedido se cargará a tu propia cuenta y lo pagas tú directamente.';
            hint.style.color = 'var(--c3)';
            esTienda = false;
            if (dtSection) dtSection.style.display = 'grid';
            if (inpFecha) inpFecha.setAttribute('required', 'required');
            if (inpHora) inpHora.setAttribute('required', 'required');
        }
        checkBonif();
    }


    function selPriceTab(el) {
        document.querySelectorAll('#price-tabs .price-tab').forEach(t=>t.classList.remove('active'));
        el.classList.add('active');
        currentCatId = parseInt(el.dataset.id);
        currentPrice = parseFloat(el.dataset.precio);
        loadCatalog(currentCatId);
    }

    function loadCatalog(catId) {
        var catalog = document.getElementById('prod-catalog');
        catalog.innerHTML = '<div style="text-align:center;padding:.5rem;font-size:.78rem;color:var(--ink3);">Cargando...</div>';
        fetch('?ajax_variedades=1&id_cat=' + catId)
            .then(r => r.json())
            .then(vars => {
                catalogVars = vars;
                if(vars.length===0) {
                    catalog.innerHTML = '<div style="text-align:center;padding:.6rem;font-size:.78rem;color:var(--ink3);">Sin variedades</div>';
                    return;
                }
                var html = '<div class="prod-grid">';
                vars.forEach(v => {
                    var inCart = cart.find(x => x.id_variedad==v.id_variedad);
                    var cls = inCart ? 'prod-card in-cart' : 'prod-card';
                    var imgHtml = v.imagen ? '<img src="'+appUrl+'/'+escHtml(v.imagen)+'">' : '<div class="pc-placeholder">🍞</div>';
                    html += '<div class="'+cls+'" id="pcard-'+v.id_variedad+'">'
                        + '<div onclick="tapProduct('+v.id_variedad+')">'
                        + imgHtml
                        + '<div class="pc-action">'+(inCart?'✅ En carrito':'<i class="bi bi-plus-circle-fill"></i> Agregar')+'</div>'
                        + '<div class="pc-name" title="'+escHtml(v.nombre)+'">'+escHtml(v.nombre)+'</div>'
                        + '</div>'
                        + '<div class="pc-form">'
                        + '<div class="pf-row"><label>Cant.</label><input type="number" class="pf-cant" min="1" max="99" value="1" oninput="this.value=this.value.replace(/[^0-9]/g,\'\').slice(0,2)" onclick="event.stopPropagation()"></div>'
                        + '<button type="button" class="pf-add" onclick="event.stopPropagation();addToCart('+v.id_variedad+')"><i class="bi bi-cart-plus"></i> Al carrito</button>'
                        + '</div>'
                        + '</div>';
                });
                html += '</div>';
                catalog.innerHTML = html;
            });
    }

    function tapProduct(idVar) {
        if(cart.find(x=>x.id_variedad==idVar)) return;
        var pcard = document.getElementById('pcard-'+idVar);
        if(!pcard) return;
        document.querySelectorAll('#prod-catalog .prod-card.expanded').forEach(c=>{if(c!==pcard) c.classList.remove('expanded')});
        pcard.classList.toggle('expanded');
    }

    function addToCart(idVar) {
        var v = catalogVars.find(x=>x.id_variedad==idVar);
        if(!v) return;
        var pcard = document.getElementById('pcard-'+idVar);
        var cant = parseInt(pcard.querySelector('.pf-cant').value) || 0;
        if(cant <= 0) return;
        if(cant > 99) cant = 99; // Límite de 99
        cart.push({id_variedad: idVar, nombre: v.nombre, imagen: v.imagen, precio: currentPrice, cantidad: cant});
        pcard.classList.remove('expanded');
        pcard.classList.add('in-cart');
        pcard.querySelector('.pc-action').innerHTML = '✅ En carrito';
        renderCart();
    }

    function removeFromCart(idVar) {
        cart = cart.filter(x=>x.id_variedad != idVar);
        var pcard = document.getElementById('pcard-'+idVar);
        if(pcard){ pcard.classList.remove('in-cart'); pcard.classList.remove('expanded'); pcard.querySelector('.pc-action').innerHTML='<i class="bi bi-plus-circle-fill"></i> Agregar'; }
        renderCart();
    }

    function renderCart() {
        var body = document.getElementById('cart-body');
        document.getElementById('cart-count').textContent = cart.length;
        var btn = document.getElementById('btn-pedido');
        var totalBar = document.getElementById('cart-total-bar');

        if(cart.length===0){
            body.innerHTML='<div class="cart-empty">Agrega productos</div>';
            totalBar.style.display='none';
            btn.disabled=true;
            document.getElementById('carrito-json').value='[]';
            checkBonif();
            return;
        }

        var html = '<div class="cart-list">';
        var totalUnd=0, totalDinero=0;
        cart.forEach(item => {
            var sub = item.cantidad * item.precio;
            totalUnd+=item.cantidad; totalDinero+=sub;
            var imgHtml = item.imagen ? '<img src="'+appUrl+'/'+item.imagen+'">' : '<div class="ci-ph">🍞</div>';
            html += '<div class="cart-item">'
                + imgHtml
                + '<div class="ci-info"><div class="ci-name">'+escHtml(item.nombre)+'</div><div class="ci-price">$'+item.precio.toLocaleString('es-CO')+'</div></div>'
                + '<div class="ci-fields"><input type="number" min="1" max="99" value="'+item.cantidad+'" oninput="this.value=this.value.replace(/[^0-9]/g,\'\').slice(0,2); updateItem('+item.id_variedad+',this.value)"></div>'
                + '<div class="ci-sub">$'+sub.toLocaleString('es-CO')+'</div>'
                + '<button type="button" class="ci-del" onclick="removeFromCart('+item.id_variedad+')"><i class="bi bi-x-lg"></i></button>'
                + '</div>';
        });
        html += '</div>';
        body.innerHTML = html;
        document.getElementById('ct-und').textContent = totalUnd;
        document.getElementById('ct-total').textContent = totalDinero.toLocaleString('es-CO');
        totalBar.style.display='block';
        btn.disabled=false;
        document.getElementById('carrito-json').value=JSON.stringify(cart);
        checkBonif();
    }

    function updateItem(idVar, val){
        var item = cart.find(x=>x.id_variedad===idVar);
        if(item){ 
            item.cantidad = Math.max(1, parseInt(val)||1);
            if(item.cantidad > 99) item.cantidad = 99; // Límite de 99
        }
        renderCart();
    }

    function checkBonif() {
        var panel = document.getElementById('bonif-panel');
        var card = document.getElementById('bonif-card');
        var titulo = document.getElementById('bonif-titulo');
        var hint = document.getElementById('bonif-hint');

        if(cart.length===0) {
            panel.style.display='none';
            bonifCredito=0;
            document.getElementById('bonif-json').value='[]';
            return;
        }

        var totalDinero=0;
        cart.forEach(it=>totalDinero+=it.cantidad*it.precio);

        if(esTienda) {
            bonifCredito = Math.floor(totalDinero/5000)*1000;
            card.style.background='rgba(21,101,192,.06)'; card.style.borderColor='rgba(21,101,192,.18)';
            titulo.style.color='#1565c0'; titulo.innerHTML='🏪 Bonificación tienda';
            hint.style.color='#1565c0'; hint.innerHTML='Tienda: $1.000 de crédito por cada $5.000. Escoge tu pan bonificado.';
        } else {
            bonifCredito = Math.floor(totalDinero/5000)*500;
            card.style.background='rgba(198,113,36,.06)'; card.style.borderColor='rgba(198,113,36,.2)';
            titulo.style.color='#c67124'; titulo.innerHTML='🎁 Ñapa mostrador';
            hint.style.color='#c67124'; hint.innerHTML='Mostrador: $500 de crédito por cada $5.000. Escoge tu pan de ñapa.';
        }

        document.getElementById('bonif-credito').textContent = bonifCredito.toLocaleString('es-CO');

        if(bonifCredito<=0) {
            panel.style.display='none';
            document.getElementById('bonif-json').value='[]';
            return;
        }
        panel.style.display='block';
        if(!bonifLoaded) loadAllVarieties();
        else updateBonifStatus();
    }

    function loadAllVarieties() {
        fetch('?ajax_all_variedades=1')
            .then(r=>r.json())
            .then(vars => {
                allVarieties = vars;
                bonifLoaded = true;
                renderBonifVars();
            });
    }

    function renderBonifVars() {
        var container = document.getElementById('bonif-varieties');
        if(allVarieties.length===0){
            container.innerHTML = '<div style="text-align:center;padding:.4rem;font-size:.72rem;color:#64b5f6;">Sin variedades</div>';
            return;
        }
        var html = '';
        allVarieties.forEach(v => {
            var imgHtml = v.imagen ? '<img src="'+appUrl+'/'+escHtml(v.imagen)+'">' : '<div class="br-ph">🍞</div>';
            html += '<div class="bonif-row">'
                + imgHtml
                + '<span class="br-name">'+escHtml(v.nombre)+'</span>'
                + '<input type="number" min="0" max="99" value="0" data-bonif-id="'+v.id_variedad+'" data-bonif-precio="'+v.precio_unitario+'" oninput="this.value=this.value.replace(/[^0-9]/g,\'\').slice(0,2); updateBonifStatus(this)">'
                + '</div>';
        });
        container.innerHTML = html;
        updateBonifStatus();
    }

    function updateBonifStatus(triggerInput = null) {
        var inputs = document.querySelectorAll('#bonif-varieties [data-bonif-id]');
        var gastado = 0, totalUnd = 0, items = [];
        
        inputs.forEach(inp => {
            var val = parseInt(inp.value)||0;
            var pr = parseFloat(inp.dataset.bonifPrecio)||0;
            if(val > 0) gastado += val*pr;
        });

        if (triggerInput && gastado > bonifCredito) {
            var prTrigger = parseFloat(triggerInput.dataset.bonifPrecio)||0;
            if (prTrigger > 0) {
                var valActual = parseInt(triggerInput.value)||0;
                var gastadoSinEste = gastado - (valActual * prTrigger);
                var maxPermitido = Math.floor((bonifCredito - gastadoSinEste) / prTrigger);
                if (maxPermitido < 0) maxPermitido = 0;
                triggerInput.value = maxPermitido;
                return updateBonifStatus();
            }
        }

        gastado = 0; 
        inputs.forEach(inp => {
            var val = parseInt(inp.value)||0;
            var pr = parseFloat(inp.dataset.bonifPrecio)||0;
            if(val > 0){
                gastado += val*pr; totalUnd += val;
                items.push({id_variedad: parseInt(inp.dataset.bonifId), cantidad: val, precio: pr});
            }
        });

        var status = document.getElementById('bonif-status');
        var pg = '$'+gastado.toLocaleString('es-CO');
        var pd = '$'+bonifCredito.toLocaleString('es-CO');
        if(gastado === bonifCredito){
            status.textContent = '✅ '+pg+'/'+pd+' · '+totalUnd+' unid.';
            status.style.background='rgba(46,125,50,.1)'; status.style.color='#2e7d32';
        } else if(gastado > bonifCredito){
            status.textContent = '⚠️ '+pg+'/'+pd+' — te pasas $'+(gastado-bonifCredito).toLocaleString('es-CO');
            status.style.background='rgba(198,40,40,.1)'; status.style.color='#c62828';
        } else {
            status.textContent = '📝 '+pg+'/'+pd+' — quedan $'+(bonifCredito-gastado).toLocaleString('es-CO');
            status.style.background= esTienda ? 'rgba(21,101,192,.08)' : 'rgba(198,113,36,.08)'; 
            status.style.color= esTienda ? '#1565c0' : '#c67124';
        }
        document.getElementById('bonif-json').value = JSON.stringify(items);
        document.getElementById('btn-pedido').disabled = (gastado > bonifCredito);
    }

    document.addEventListener("DOMContentLoaded", function() {
        if (cart.length > 0) {
            renderCart();
            let firstTab = document.querySelector('.price-tab');
            if (firstTab) firstTab.click();
            
            var checkInt = setInterval(function() {
                if (bonifLoaded) {
                    clearInterval(checkInt);
                    Object.keys(bonifPreload).forEach(function(idv) {
                        var inp = document.querySelector('#bonif-varieties [data-bonif-id="'+idv+'"]');
                        if (inp) inp.value = bonifPreload[idv];
                    });
                    updateBonifStatus();
                }
            }, 100);
        }
    });
