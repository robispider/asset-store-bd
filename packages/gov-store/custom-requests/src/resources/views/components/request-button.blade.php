<form action="{{ route('gov.requests.basket.add') }}" method="POST" class="ajax-basket-form" style="margin: 0; width: 100%;">
    @csrf
    <input type="hidden" name="item_type" value="{{ strtolower($itemType) }}">
    <input type="hidden" name="item_id" value="{{ $itemId }}">
    
    <button type="submit" class="btn btn-primary btn-sm btn-block add-to-basket-btn">
        <i class="fas fa-cart-plus"></i> {{ __('requestlabels::requests.requestbutton_btn_add_to_basket') }}
    </button>
</form>

<script>
// Attach AJAX handler once if not already attached
if (typeof window.basketAjaxInitialized === 'undefined') {
    window.basketAjaxInitialized = true;
    document.addEventListener('submit', function(e) {
        if (e.target && e.target.classList.contains('ajax-basket-form')) {
            e.preventDefault();
            let form = e.target;
            let btn = form.querySelector('button');
            let originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ __('requestlabels::requests.requestbutton_btn_adding') }}';
            btn.disabled = true;

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.innerHTML = '<i class="fas fa-check"></i> {{ __('requestlabels::requests.requestbutton_btn_added') }}';
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-success');
                    
                    // Update floating basket badge count
                    let badge = document.getElementById('floating-basket-count');
                    if (badge) badge.innerText = data.count;

                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-primary');
                        btn.disabled = false;
                    }, 1500);
                } else {
                    alert('{{ __('requestlabels::requests.requestbutton_ajax_error') }}' || 'Error adding item');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(err => {
                form.submit(); // Fallback to normal submission if AJAX fails
            });
        }
    });
}
</script>