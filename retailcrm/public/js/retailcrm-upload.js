$(function(){
    function RetailcrmUploadForm() {
        this.form = $('input[value="retailcrm_upload_form"]').parent().get(0);

        if (typeof this.form === 'undefined') {
            return false;
        }

        this.input = $(this.form).find('input[name="RETAILCRM_UPLOAD_ORDERS_ID"]').get(0);
        this.submitButton = $(this.form).find('button[type="submit"]').get(0);
        this.messageContainer = document.querySelector('#content > div.bootstrap + div.bootstrap');
        this.submitAction = this.submitAction.bind(this);
        this.partitionId = this.partitionId.bind(this);
        this.setLoading = this.setLoading.bind(this);

        $(this.submitButton).click(this.submitAction);
    }

    RetailcrmUploadForm.prototype.submitAction = function (event) {
        event.preventDefault();
        let ids = this.partitionId($(this.input).val().toString().replace(/\s+/g, ''));

        if (ids.length === 0) {
            return false;
        }

        this.setLoading(true);
        $(this.form).submit();
    }

    RetailcrmUploadForm.prototype.setLoading = function (loading) {
        let indicator = $('div#retailcrm-loading-fade');

        if (indicator.length === 0) {
            $('body').append(`
            <div id="retailcrm-loading-fade">
                <div id="retailcrm-loader"></div>
            </div>
            `.trim());
        }

        indicator.css('visibility', (loading ? 'visible' : 'hidden'));
    }

    RetailcrmUploadForm.prototype.partitionId = function (idList) {
        if (idList === '') {
            return [];
        }

        let itemsList = idList.split(',');
        let ids = itemsList.filter(item => item.toString().indexOf('-') === -1);
        let ranges = itemsList.filter(item => item.toString().indexOf('-') !== -1);
        let resultRanges = [];

        ranges.forEach(item => {
            let rangeData = item.split('-');

            resultRanges = [...resultRanges, ...[...Array(+rangeData[1] + 1)
                .keys()].slice(+rangeData[0], +rangeData[1] + 1)];
        });

        return [...ids, ...resultRanges].map(item => +item).sort((a, b) => a - b);
    }

    new RetailcrmUploadForm();
});