<div class="modal fade" id="ShopUrlModal" tabindex="-1" role="dialog" aria-labelledby="ShopUrlModalLabel">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ShopUrlModalLabel">{{ __('shop.referral_link') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <strong id="modalShopUrl"></strong>
            </div>
            <div class="modal-footer">
                <form>
                    <button type="button" class="btn bg-gradient-secondary" data-dismiss="modal">{{ __('messages.cancel') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
@endpush
@push('js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        $(document).on('click', '.shop-link-btn', async function () {
            var id = $(this).data('item-id');
            try {
                document.getElementById('modalShopUrl').innerHTML = "";
                $.ajax({
                    url: "{{ route('admin.shop.downloadlink', ':id') }}".replace(':id', id),
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function (res) {
                        if (res.status) {
                            document.getElementById('modalShopUrl').innerHTML = res.download;
                        } else {
                            document.getElementById('modalShopUrl').innerHTML = res.error;
                        }
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                        document.getElementById('modalShopUrl').innerHTML = `{{ __('messages.unexpected_error') }}`;
                    }
                });
            } catch (error) {
                console.error(error);
                document.getElementById('modalShopUrl').innerHTML = `{{ __('messages.unexpected_error') }}`;
            }
        });
    });
</script>
@endpush