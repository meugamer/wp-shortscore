$( document ).ready(function() {
    $('input[type="range"]').rangeslider({ polyfill : false,
        onInit : function() {
            this.output = $( '.shortscore-hreview .rating .value' ).html( this.$element.val() );
        },
        onSlide : function( position, value ) {
           // this.output.html( value );
            valueOutput(value);
        }});


    function valueOutput(element) {
        if (element.value === undefined) {
            var value = element;
        } else {
            var value = element.value;
        }
            var output = document.getElementById('shortscore_value');
        $('#score').val(value);
        output.innerHTML = value;
        var classValue = Math.round(value);
        $('#shortscore_value').removeClass().addClass('shortscore shortscore-' + classValue);
    }
});

