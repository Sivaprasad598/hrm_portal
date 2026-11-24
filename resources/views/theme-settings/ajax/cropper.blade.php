<style>
    #imageCropper {
        height: 350px;
    }
</style>

<div class="modal-header">
    <h5 class="modal-title">@lang('app.cropImage')</h5>
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
</div>
<div class="modal-body">
    <div class="row d-flex align-content-center justify-content-center">
        <img id="imageCropper" src="" alt="Picture">
    </div>
</div>
<div class="modal-footer">
    <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.cancel')</x-forms.button-cancel>
    <x-forms.button-primary id="cropImage" icon="crop">@lang('app.crop')</x-forms.button-primary>
    {{-- own profile: crop and save the picture in one step (shown when the page can save it alone) --}}
    <x-forms.button-primary id="cropSaveImage" icon="check" class="ml-2 d-none">Crop &amp; save</x-forms.button-primary>
</div>

<script>
    var elementId = '{{ $element }}';
    // A person's photo (own profile, employee form): a square, saved as a 512 x 512 JPEG (about
    // 50-150 KB). Logos and other pictures keep their shape and their transparency (PNG), at most
    // 1600 px a side, and become a JPEG only if the PNG would pass the 2 MB upload limit.
    var isPhoto = ['profile-image', 'image'].indexOf(elementId) !== -1;
    var img = document.getElementById('imageCropper');
    var cropper;
    var canvas;
    // logo id input file and set to image
    var input = document.getElementById(elementId);
    var files = input.files;

    /** The cropped picture as a data URL small enough to upload. */
    function croppedDataURL() {
        if (isPhoto) {
            var square = cropper.getCroppedCanvas({ width: 512, height: 512, fillColor: '#fff', imageSmoothingQuality: 'high' });
            return square.toDataURL('image/jpeg', 0.9);
        }

        var c = cropper.getCroppedCanvas({ maxWidth: 1600, maxHeight: 1600, imageSmoothingQuality: 'high' });
        var png = c.toDataURL('image/png');

        // a data URL is about 4/3 of the file; stay under the 2 MB limit
        if (png.length * 0.75 < 1.9 * 1024 * 1024) {
            return png;
        }

        var flat = document.createElement('canvas');
        flat.width = c.width; flat.height = c.height;
        var ctx = flat.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, flat.width, flat.height); ctx.drawImage(c, 0, 0);

        return flat.toDataURL('image/jpeg', 0.88);
    }

    function dataURLtoFile(dataurl) {

        var arr = dataurl.split(','),
            mime = arr[0].match(/:(.*?);/)[1],
            bstr = atob(arr[1]),
            n = bstr.length,
            u8arr = new Uint8Array(n);

        while(n--){
            u8arr[n] = bstr.charCodeAt(n);
        }

        var ext = mime === 'image/jpeg' ? '.jpg' : '.png';

        return new File([u8arr], Math.random().toString(36).substr(2, 10) + ext, {
            type: mime,
            lastModified: Date.now()
        });
    }

    if (files.length > 0) {
        var file = files[0];
        var reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;

            // delay to load image
            setTimeout(function () {
                cropper = new Cropper(img, isPhoto
                    ? { viewMode: 1, aspectRatio: 1, autoCropArea: 0.9 }
                    : { viewMode: 1 });
            }, 200);

        }
        reader.readAsDataURL(file);
    }

    // "Crop & save" only where the page saves the picture on its own (Profile -> "Save photo")
    if (elementId === 'profile-image' && $('#save-photo').length) {
        $('#cropSaveImage').removeClass('d-none');
    }

    $('#cropSaveImage').click(function () {
        var saveFor = elementId;
        $('#cropSaveImage').attr('disabled', true);
        $('#cropImage').trigger('click');
        if (saveFor) { $('#save-photo').trigger('click'); }
    });

    $('#cropImage').click(function () {
        if (!elementId) { return; }
        $('#cropImage').attr('disabled', true);
        var dataUrl = croppedDataURL();

        // set the new file to the input file on the element
        let container = new DataTransfer();
        container.items.add(dataURLtoFile(dataUrl));
        input.files = container.files;

        // change dropify image
        $('#' + elementId).parent().find('.dropify-render img').attr('src', dataUrl);

        // the page may offer to save the picture on its own (profile: "Save photo")
        $('#' + elementId).trigger('cropper:done', [dataUrl]);

        // close modal
        elementId = '';
        $(MODAL_LG).modal('hide');
    });

    function onModelClose() {
        if(elementId != undefined && elementId != '') {
            $('#' + elementId).parent().find('.dropify-clear').click();
            cropper.destroy();
            elementId = '';
        }
    }

    $(MODAL_LG).on('hidden.bs.modal', function (e) {
        onModelClose();
        $(MODAL_LG + ' .modal-content').html('');
        $(MODAL_LG).off('hidden.bs.modal');
    });
</script>
