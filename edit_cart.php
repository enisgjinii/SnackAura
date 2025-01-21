<?php
$products_by_id = [];
foreach ($products as $p) {
    $products_by_id[$p['id']] = $p;
}
$products = $products_by_id;
?>

<?php foreach ($_SESSION['cart'] as $index => $item): ?>
    <?php if (!isset($products[$item['product_id']])) continue; ?>
    <?php $product = $products[$item['product_id']]; ?>
    <div class="modal fade" id="editCartModal<?= $index ?>" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="index.php" class="edit-cart-form" data-baseprice="<?= number_format((float)($product['base_price'] ?? 0), 2, '.', '') ?>" data-itemindex="<?= $index ?>" data-basemaxextras="<?= $product['max_extras_base'] ?? 0 ?>" data-basemaxsauces="<?= $product['max_sauces_base'] ?? 0 ?>" data-basemaxdresses="<?= $product['max_dresses_base'] ?? 0 ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit <?= htmlspecialchars($item['name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control quantity-selector" name="quantity" value="<?= htmlspecialchars($item['quantity']) ?>" min="1" max="99" required>
                        </div>
                        <?php
                        $hasSizes = !empty($product['sizes']);
                        $chosenSize = $item['size'] ?? '';
                        ?>
                        <?php if ($hasSizes): ?>
                            <div class="mb-3">
                                <label class="form-label">Size</label>
                                <select class="form-select size-selector" name="size">
                                    <option value="">Choose Size</option>
                                    <?php foreach ($product['sizes'] as $sz):
                                        $selected = ($chosenSize === ($sz['size'] ?? '')) ? 'selected' : '';
                                        $mxEx = (int)($sz['max_extras'] ?? 0);
                                        $mxSa = (int)($sz['max_sauces'] ?? 0);
                                        $mxDr = (int)($sz['max_dresses'] ?? 0);
                                        ?>
                                        <option value="<?= htmlspecialchars($sz['size']) ?>"
                                                data-sizeprice="<?= number_format((float)($sz['price'] ?? 0), 2, '.', '') ?>"
                                                data-sizes-extras='<?= json_encode($sz['extras'] ?? []) ?>'
                                                data-sizes-sauces='<?= json_encode($sz['sauces'] ?? []) ?>'
                                                data-sizes-dresses='<?= json_encode($sz['dresses'] ?? []) ?>'
                                                data-maxextras="<?= $mxEx ?>"
                                                data-maxsauces="<?= $mxSa ?>"
                                                data-maxdresses="<?= $mxDr ?>"
                                                <?= $selected ?>>
                                            <?= htmlspecialchars($sz['size']) ?> (+<?= number_format((float)($sz['price'] ?? 0), 2) ?>€)
                                            <?php
                                            if ($mxEx > 0) echo " - Max $mxEx extras";
                                            if ($mxSa > 0) echo " - Max $mxSa sauces";
                                            if ($mxDr > 0) echo " - Max $mxDr dresses";
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <?php
                        $actualExtras = $item['extras'] ?? [];
                        $actualSauces = $item['sauces'] ?? [];
                        $actualDresses = $item['dresses'] ?? [];
                        function findQuantity($name, $arr) {
                            foreach ($arr as $x) {
                                if (!empty($x['name']) && $x['name'] === $name['name']) return (int)($x['quantity'] ?? 0);
                            }
                            return 0;
                        }
                        ?>
                        <div class="mb-3">
                            <?php if (!$hasSizes && ($product['max_extras_base'] || $product['max_sauces_base'] || $product['max_dresses_base'])): ?>
                                <p style="font-size:.85rem" class="text-info">
                                    <?php if (!empty($product['max_extras_base'])) echo "Max Extras: ".$product['max_extras_base']." "; ?>
                                    <?php if (!empty($product['max_sauces_base'])) echo "Max Sauces: ".$product['max_sauces_base']." "; ?>
                                    <?php if (!empty($product['max_dresses_base'])) echo "Max Dresses: ".$product['max_dresses_base']; ?>
                                </p>
                            <?php endif; ?>
                            <label class="form-label">Extras</label>
                            <div class="extras-container">
                                <?php foreach ($product['extras'] as $ex): ?>
                                    <?php $qVal = findQuantity($ex, $actualExtras); ?>
                                    <div class="row align-items-center mb-2">
                                        <div class="col-auto">
                                            <input type="checkbox" class="form-check-input extra-checkbox"
                                                   id="edit-check-extra-<?= htmlspecialchars($ex['name']) ?>-<?= $index ?>"
                                                   data-qtyid="edit-qty-extra-<?= htmlspecialchars($ex['name']) ?>-<?= $index ?>"
                                                   <?= $qVal > 0 ? 'checked' : '' ?>>
                                        </div>
                                        <div class="col">
                                            <label class="form-check-label" for="edit-check-extra-<?= htmlspecialchars($ex['name']) ?>-<?= $index ?>">
                                                <?= htmlspecialchars($ex['name']) ?> (+<?= number_format((float)($ex['price'] ?? 0), 2) ?>€)
                                            </label>
                                        </div>
                                        <div class="col-auto">
                                            <div class="input-group input-group-sm" style="width:100px;">
                                                <button type="button" class="btn btn-outline-secondary minus-btn" data-target="edit-qty-extra-<?= htmlspecialchars($ex['name']) ?>-<?= $index ?>">-</button>
                                                <input type="number" class="form-control text-center extra-quantity edit-extra-quantity" name="extras[<?= htmlspecialchars($ex['name']) ?>]" id="edit-qty-extra-<?= htmlspecialchars($ex['name']) ?>-<?= $index ?>" data-price="<?= number_format((float)$ex['price'], 2, '.', '') ?>" value="<?= $qVal ?>" min="0" step="1" <?= $qVal>0?'':'disabled' ?>>
                                                <button type="button" class="btn btn-outline-secondary plus-btn" data-target="edit-qty-extra-<?= htmlspecialchars($ex['name']) ?>-<?= $index ?>">+</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sauces</label>
                            <div class="sauces-container">
                                <?php foreach ($product['sauces'] as $sc): ?>
                                    <?php $qVal = findQuantity($sc, $actualSauces); ?>
                                    <div class="row align-items-center mb-2">
                                        <div class="col-auto">
                                            <input type="checkbox" class="form-check-input extra-checkbox"
                                                   id="edit-check-sauce-<?= htmlspecialchars($sc['name']) ?>-<?= $index ?>"
                                                   data-qtyid="edit-qty-sauce-<?= htmlspecialchars($sc['name']) ?>-<?= $index ?>"
                                                   <?= $qVal > 0 ? 'checked' : '' ?>>
                                        </div>
                                        <div class="col">
                                            <label class="form-check-label" for="edit-check-sauce-<?= htmlspecialchars($sc['name']) ?>-<?= $index ?>">
                                                <?= htmlspecialchars($sc['name']) ?> (+<?= number_format((float)($sc['price'] ?? 0), 2) ?>€)
                                            </label>
                                        </div>
                                        <div class="col-auto">
                                            <div class="input-group input-group-sm" style="width:100px;">
                                                <button type="button" class="btn btn-outline-secondary minus-btn" data-target="edit-qty-sauce-<?= htmlspecialchars($sc['name']) ?>-<?= $index ?>">-</button>
                                                <input type="number" class="form-control text-center extra-quantity sauce-quantity" name="sauces[<?= htmlspecialchars($sc['name']) ?>]" id="edit-qty-sauce-<?= htmlspecialchars($sc['name']) ?>-<?= $index ?>" data-price="<?= number_format((float)$sc['price'], 2, '.', '') ?>" value="<?= $qVal ?>" min="0" step="1" <?= $qVal>0?'':'disabled' ?>>
                                                <button type="button" class="btn btn-outline-secondary plus-btn" data-target="edit-qty-sauce-<?= htmlspecialchars($sc['name']) ?>-<?= $index ?>">+</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dresses</label>
                            <div class="dresses-container">
                                <?php foreach ($product['dresses'] as $dr): ?>
                                    <?php $qVal = findQuantity($dr, $actualDresses); ?>
                                    <div class="row align-items-center mb-2">
                                        <div class="col-auto">
                                            <input type="checkbox" class="form-check-input extra-checkbox"
                                                   id="edit-check-dress-<?= htmlspecialchars($dr['name']) ?>-<?= $index ?>"
                                                   data-qtyid="edit-qty-dress-<?= htmlspecialchars($dr['name']) ?>-<?= $index ?>"
                                                   <?= $qVal > 0 ? 'checked' : '' ?>>
                                        </div>
                                        <div class="col">
                                            <label class="form-check-label" for="edit-check-dress-<?= htmlspecialchars($dr['name']) ?>-<?= $index ?>">
                                                <?= htmlspecialchars($dr['name']) ?> (+<?= number_format((float)($dr['price'] ?? 0), 2) ?>€)
                                            </label>
                                        </div>
                                        <div class="col-auto">
                                            <div class="input-group input-group-sm" style="width:100px;">
                                                <button type="button" class="btn btn-outline-secondary minus-btn" data-target="edit-qty-dress-<?= htmlspecialchars($dr['name']) ?>-<?= $index ?>">-</button>
                                                <input type="number" class="form-control text-center extra-quantity" name="dresses[<?= htmlspecialchars($dr['name']) ?>]" id="edit-qty-dress-<?= htmlspecialchars($dr['name']) ?>-<?= $index ?>" data-price="<?= number_format((float)$dr['price'], 2, '.', '') ?>" value="<?= $qVal ?>" min="0" step="1" <?= $qVal>0?'':'disabled' ?>>
                                                <button type="button" class="btn btn-outline-secondary plus-btn" data-target="edit-qty-dress-<?= htmlspecialchars($dr['name']) ?>-<?= $index ?>">+</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if (!empty($drinks)): ?>
                            <div class="mb-3">
                                <label class="form-label">Drink</label>
                                <select class="form-select drink-selector" name="drink">
                                    <option value="">Choose Drink</option>
                                    <?php foreach ($drinks as $dk):
                                        $sel = (!empty($item['drink']['id']) && $item['drink']['id'] == $dk['id']) ? 'selected' : '';
                                        ?>
                                        <option value="<?= htmlspecialchars($dk['id']) ?>" data-drinkprice="<?= number_format((float)$dk['price'], 2, '.', '') ?>" <?= $sel ?>>
                                            <?= htmlspecialchars($dk['name']) ?> (+<?= number_format((float)$dk['price'], 2) ?>€)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Special Instructions</label>
                            <textarea class="form-control" name="special_instructions" rows="2"><?= htmlspecialchars($item['special_instructions']) ?></textarea>
                        </div>
                        <div class="bg-light p-2 rounded">
                            <small><strong>Estimated Price:</strong></small>
                            <span class="edit-estimated-price-<?= $index ?>" style="font-size:1rem;color:#d3b213"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="item_index" value="<?= $index ?>">
                        <input type="hidden" name="update_cart" value="1">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Cart</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded',function(){
    let forms=document.querySelectorAll('.edit-cart-form');
    window.lastChangedInputEdit={extras:null,sauces:null,dresses:null};
    function limitItemQuantitiesEdit(form,type){
        let sizeSel=form.querySelector('.size-selector');
        let baseMax=parseInt(form.dataset['basemax'+type]||"0",10);
        let maxVal=0;
        if(sizeSel&&sizeSel.value){
            let sOpt=sizeSel.selectedOptions[0];
            maxVal=parseInt(sOpt.dataset['max'+type]||"0",10);
        } else {
            maxVal=baseMax;
        }
        if(!maxVal||maxVal<1)return;
        let cls=type==="sauces"?".sauce-quantity":".edit-extra-quantity";
        if(type==="extras") cls=".edit-extra-quantity:not(.sauce-quantity)";
        if(type==="dresses") cls=".dresses-container .extra-quantity";
        let inputs=form.querySelectorAll(cls);
        let total=0;
        inputs.forEach(inp=>{total+=parseInt(inp.value||'0',10);});
        if(total>maxVal){
            alert("Max "+maxVal+" "+type+" allowed!");
            if(window.lastChangedInputEdit[type]){
                let cval=parseInt(window.lastChangedInputEdit[type].value||'0',10),diff=total-maxVal;
                let revertVal=Math.max(0,cval-diff);
                window.lastChangedInputEdit[type].value=revertVal;
            }
        }
    }
    function updateEstimatedPriceEdit(form){
        let base=parseFloat(form.dataset.baseprice||"0");
        let idx=form.dataset.itemindex;
        let display=document.querySelector('.edit-estimated-price-'+idx);
        let q=parseFloat(form.querySelector('.quantity-selector').value||"1");
        if(q<1)q=1;
        let sz=form.querySelector('.size-selector');
        let sp=(sz&&sz.value)?parseFloat(sz.selectedOptions[0].dataset.sizeprice||"0"):0;
        let totalExtras=0;
        form.querySelectorAll('.extra-quantity').forEach(iq=>{
            let ip=parseFloat(iq.dataset.price||"0"),iv=parseFloat(iq.value||"0");
            if(iv>0) totalExtras+=(ip*iv);
        });
        let dr=form.querySelector('.drink-selector');
        let dp=(dr&&dr.value)?parseFloat(dr.selectedOptions[0].dataset.drinkprice||"0"):0;
        let partial=(base+sp+totalExtras+dp)*q;
        if(partial<0)partial=0;
        if(display) display.textContent=partial.toFixed(2)+"€";
    }
    function updateSizeSpecificOptionsEdit(form,sd){
        let se=[],ss=[],dd=[];
        if(sd.sizesExtras){try{se=JSON.parse(sd.sizesExtras);}catch(e){}}
        if(sd.sizesSauces){try{ss=JSON.parse(sd.sizesSauces);}catch(e){}}
        if(sd.sizesDresses){try{dd=JSON.parse(sd.sizesDresses);}catch(e){}}
        let ec=form.querySelector('.extras-container');
        if(ec){
            ec.innerHTML='';
            se.forEach(e=>{
                ec.innerHTML+=`
<div class="row align-items-center mb-2">
 <div class="col-auto">
  <input type="checkbox" class="form-check-input extra-checkbox" id="dynamic-ex-check-${e.name}" data-qtyid="dynamic-ex-qty-${e.name}" >
 </div>
 <div class="col">
  <label class="form-check-label" for="dynamic-ex-check-${e.name}">${e.name} (+${parseFloat(e.price).toFixed(2)}€)</label>
 </div>
 <div class="col-auto">
  <div class="input-group input-group-sm" style="width:100px;">
   <button type="button" class="btn btn-outline-secondary minus-btn" data-target="dynamic-ex-qty-${e.name}">-</button>
   <input type="number" class="form-control text-center extra-quantity edit-extra-quantity" name="extras[${e.name}]" id="dynamic-ex-qty-${e.name}" data-price="${parseFloat(e.price).toFixed(2)}" value="0" min="0" step="1" disabled>
   <button type="button" class="btn btn-outline-secondary plus-btn" data-target="dynamic-ex-qty-${e.name}">+</button>
  </div>
 </div>
</div>`;
            });
        }
        let sc=form.querySelector('.sauces-container');
        if(sc){
            sc.innerHTML='';
            ss.forEach(e=>{
                sc.innerHTML+=`
<div class="row align-items-center mb-2">
 <div class="col-auto">
  <input type="checkbox" class="form-check-input extra-checkbox" id="dynamic-sc-check-${e.name}" data-qtyid="dynamic-sc-qty-${e.name}">
 </div>
 <div class="col">
  <label class="form-check-label" for="dynamic-sc-check-${e.name}">${e.name} (+${parseFloat(e.price).toFixed(2)}€)</label>
 </div>
 <div class="col-auto">
  <div class="input-group input-group-sm" style="width:100px;">
   <button type="button" class="btn btn-outline-secondary minus-btn" data-target="dynamic-sc-qty-${e.name}">-</button>
   <input type="number" class="form-control text-center extra-quantity sauce-quantity" name="sauces[${e.name}]" id="dynamic-sc-qty-${e.name}" data-price="${parseFloat(e.price).toFixed(2)}" value="0" min="0" step="1" disabled>
   <button type="button" class="btn btn-outline-secondary plus-btn" data-target="dynamic-sc-qty-${e.name}">+</button>
  </div>
 </div>
</div>`;
            });
        }
        let dc=form.querySelector('.dresses-container');
        if(dc){
            dc.innerHTML='';
            dd.forEach(e=>{
                dc.innerHTML+=`
<div class="row align-items-center mb-2">
 <div class="col-auto">
  <input type="checkbox" class="form-check-input extra-checkbox" id="dynamic-dr-check-${e.name}" data-qtyid="dynamic-dr-qty-${e.name}">
 </div>
 <div class="col">
  <label class="form-check-label" for="dynamic-dr-check-${e.name}">${e.name} (+${parseFloat(e.price).toFixed(2)}€)</label>
 </div>
 <div class="col-auto">
  <div class="input-group input-group-sm" style="width:100px;">
   <button type="button" class="btn btn-outline-secondary minus-btn" data-target="dynamic-dr-qty-${e.name}">-</button>
   <input type="number" class="form-control text-center extra-quantity" name="dresses[${e.name}]" id="dynamic-dr-qty-${e.name}" data-price="${parseFloat(e.price).toFixed(2)}" value="0" min="0" step="1" disabled>
   <button type="button" class="btn btn-outline-secondary plus-btn" data-target="dynamic-dr-qty-${e.name}">+</button>
  </div>
 </div>
</div>`;
            });
        }
    }
    function initializeEditEvents(form){
        form.querySelectorAll('.extra-checkbox').forEach(checkbox=>{
            checkbox.addEventListener('change',function(){
                const qtyId=checkbox.dataset.qtyid;
                const inp=document.getElementById(qtyId);
                if(checkbox.checked){
                    inp.disabled=false;
                    if(parseInt(inp.value)===0) inp.value=1;
                } else {
                    inp.value=0;inp.disabled=true;
                }
                updateEstimatedPriceEdit(form);
            });
        });
        form.querySelectorAll('.plus-btn').forEach(btn=>{
            btn.addEventListener('click',function(){
                const qtyId=btn.dataset.target;
                const inp=document.getElementById(qtyId);
                let cv=parseInt(inp.value)||0;
                inp.value=cv+1;
                if(inp.value>0){
                    form.querySelectorAll('.extra-checkbox').forEach(chk=>{
                        if(chk.dataset.qtyid===qtyId) chk.checked=true;
                    });
                    inp.disabled=false;
                }
                updateEstimatedPriceEdit(form);
                if(inp.classList.contains('sauce-quantity')){window.lastChangedInputEdit['sauces']=inp;limitItemQuantitiesEdit(form,'sauces');}
                if(qtyId.includes('extra-')){window.lastChangedInputEdit['extras']=inp;limitItemQuantitiesEdit(form,'extras');}
                if(qtyId.includes('dress-')){window.lastChangedInputEdit['dresses']=inp;limitItemQuantitiesEdit(form,'dresses');}
            });
        });
        form.querySelectorAll('.minus-btn').forEach(btn=>{
            btn.addEventListener('click',function(){
                const qtyId=btn.dataset.target;
                const inp=document.getElementById(qtyId);
                let cv=parseInt(inp.value)||0;
                if(cv>0) inp.value=cv-1;
                if(parseInt(inp.value)===0){
                    form.querySelectorAll('.extra-checkbox').forEach(chk=>{
                        if(chk.dataset.qtyid===qtyId) chk.checked=false;
                    });
                    inp.disabled=true;
                }
                updateEstimatedPriceEdit(form);
                if(inp.classList.contains('sauce-quantity')){window.lastChangedInputEdit['sauces']=inp;limitItemQuantitiesEdit(form,'sauces');}
                if(qtyId.includes('extra-')){window.lastChangedInputEdit['extras']=inp;limitItemQuantitiesEdit(form,'extras');}
                if(qtyId.includes('dress-')){window.lastChangedInputEdit['dresses']=inp;limitItemQuantitiesEdit(form,'dresses');}
            });
        });
        form.querySelectorAll('.extra-quantity').forEach(inp=>{
            inp.addEventListener('input',function(){
                let val=parseInt(inp.value)||0;
                if(val>0){
                    form.querySelectorAll('.extra-checkbox').forEach(chk=>{
                        if(chk.dataset.qtyid===inp.id) chk.checked=true;
                    });
                    inp.disabled=false;
                } else {
                    form.querySelectorAll('.extra-checkbox').forEach(chk=>{
                        if(chk.dataset.qtyid===inp.id) chk.checked=false;
                    });
                    inp.disabled=true;
                }
                updateEstimatedPriceEdit(form);
                if(inp.classList.contains('sauce-quantity')){window.lastChangedInputEdit['sauces']=inp;limitItemQuantitiesEdit(form,'sauces');}
                if(inp.id.includes('extra-')){window.lastChangedInputEdit['extras']=inp;limitItemQuantitiesEdit(form,'extras');}
                if(inp.id.includes('dress-')){window.lastChangedInputEdit['dresses']=inp;limitItemQuantitiesEdit(form,'dresses');}
            });
        });
        let sz=form.querySelector('.size-selector');
        if(sz){
            sz.addEventListener('change',function(){
                let sd={
                    sizesExtras:this.selectedOptions[0]?.dataset.sizesExtras||'[]',
                    sizesSauces:this.selectedOptions[0]?.dataset.sizesSauces||'[]',
                    sizesDresses:this.selectedOptions[0]?.dataset.sizesDresses||'[]'
                };
                updateSizeSpecificOptionsEdit(form,sd);
                form.querySelectorAll('.extra-checkbox').forEach(cb=>{
                    cb.checked=false;
                    let qid=cb.dataset.qtyid;
                    if(qid){
                        let iq=document.getElementById(qid);
                        iq.value=0;iq.disabled=true;
                    }
                });
                initializeEditEvents(form);
                updateEstimatedPriceEdit(form);
            });
        }
        let dr=form.querySelector('.drink-selector');
        if(dr){
            dr.addEventListener('change',()=>updateEstimatedPriceEdit(form));
        }
        let qty=form.querySelector('.quantity-selector');
        if(qty){
            qty.addEventListener('change',()=>updateEstimatedPriceEdit(form));
        }
        updateEstimatedPriceEdit(form);
    }
    forms.forEach(f=>{initializeEditEvents(f);});
});
</script>
