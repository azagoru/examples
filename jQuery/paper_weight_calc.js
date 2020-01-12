/*
* http://dev.azartel.ru/calc
* */

jQuery(function ($) {
    "use strict";

    let inputs  = { // for slider: from, to, step
            width       : [1,1000,1],
            length      : [1,1000,1],
            density     : [1,1000,1],
            quantity    : [1,1000000,1],
            add_weight  : [0,100,1],
            copies      : [0,1000,250],
        },
        paperSizes = { // width, height, density
            a1      : [594,841,100],
            a2      : [420,594,100],
            a3      : [297,420,100],
            a4      : [210,297,100],
            a5      : [148,210,100],
            a6      : [105,148,100],
            long    : [105,210,100],
        };

    let buttons = $('.calc-buttons button'),
        addButtons = $('.add-buttons button');

    let toolTipOptions = {
        position: { my: "left-25 bottom-25", at: "bottom center" }
    };

    // labels
    $('#add_weight_button').tooltip(toolTipOptions);
    $('#copies_button').tooltip(toolTipOptions);
    $('#width_label').tooltip(toolTipOptions);
    $('#length_label').tooltip(toolTipOptions);
    $('#density_label').tooltip(toolTipOptions);
    $('#quantity_label').tooltip(toolTipOptions);
    $('#additional_weight_label').tooltip(toolTipOptions);
    $('#copies_label').tooltip(toolTipOptions);

    for (let name in inputs) {
        if (inputs.hasOwnProperty(name))
        {
            let slider  = $('#' + name),
                input   = $('#' + name + '_value'),
                options = inputs[name];

            // slider init
            slider.slider({
                min:    options[0],
                max:    options[1],
                step:   options[2],
                slide: function (event, ui) {
                    input.val(ui.value);

                    if ( (name === 'width') || (name === 'length') ) {
                        $('.paper-size-container').addClass('disabled');
                        $('#paper_size').html('');
                    }

                    calc();
                }
            });

            // validation
            input.keyup(function ()
            {
                if ( (name === 'width') || (name === 'length') ) {
                    $('.paper-size-container').addClass('disabled');
                    $('#paper_size').html('');
                }

                if (validate(input, slider, options)) {
                    input.val('').addClass('error');
                } else {
                    input.removeClass('error');

                    calc();
                }
            });
        }
    }

    $('#add_set').click(function()
    {
        let values = {};
        for (let name in inputs)
            if (inputs.hasOwnProperty(name))
                values[name] = parseInt($('#' + name + '_value').val());

        let addWeight   = !$('#add_weight').parent('div').hasClass('disabled'),
            copies      = !$('#copies').parent('div').hasClass('disabled'),
            paperSize   = $('#paper_size').html().trim(),
            copyWeight  = $('#weight').html().trim() + ' ' + $('#weight_unit').html().trim(),
            printWeight = $('#print_weight').html().trim();

        printWeight = (printWeight === '0')
            ? 0
            : printWeight  + ' ' + $('#print_weight_unit').html().trim();

        let html = '';

        html += '<tr>';

        paperSize
            ? html += '<td class="paper-size">' + paperSize + '</td>'
            : html += '<td></td>';

        html += '<td>';

            html += values['width'] + ' mm x ' + values['length'] + ' mm';
            html += ' x ' + values['density'] + ' g/m<sup>2</sup>';
            html += ' x ' + values['quantity'] + '';

            if (addWeight)
                html += ' + ' + values['add_weight'] + ' g';

        html += '</td>';

        printWeight
            ? html += '<td>' + copyWeight + '</td>'
            : html += '<td><strong>' + copyWeight + '</strong></td>';

        copies
            ? html += '<td> x ' + values['copies'] + '</td>'
            : html += '<td></td>';

        printWeight
            ? html += '<td><strong>' + printWeight + '</strong></td>'
            : html += '<td></td>';

        html += '<td><a href="#" class="remove-set">x</a></td>';

        html += '</tr>';

        $('#sets tbody').append(html);

        calcSetsTotal();


    });

    $('#sets tbody').on('click', '.remove-set', function (e)
    {
        e.preventDefault();

        $(this).closest('tr').remove();

        calcSetsTotal();
    });

    function calcSetsTotal()
    {
        let totalWeight = 0;

        $('#sets tbody').find('tr').each(function() {
            let weightHtml = $(this).find('strong').html(),
                weight = weightHtml.split(' ');

            if (weight[1] === 'kg')
                weight = parseInt(parseFloat(weight[0]) * 1000); // todo something
            else
                weight = parseFloat(weight[0]);

            totalWeight += weight;
        });

        totalWeight = getWeight(totalWeight);

        $('#sets_total').html(totalWeight['weight'] + '&nbsp;' + totalWeight['unit']);
    }

    buttons.click(function()
    {
        let paperSize = $(this).data('size');

        initDefaultPaper(paperSize);
    });

    addButtons.click(function()
    {
        let sliderId        = $(this).data('slider-id'),
            sliderContainer = $('#' + sliderId).parent('div');

        if (sliderContainer.hasClass('disabled')) {
            sliderContainer.removeClass('disabled');
            $(this).addClass('selected');

            if (sliderId === 'copies')
                $('.calc-total-print')
                    .removeClass('disabled');
        } else {
            sliderContainer.addClass('disabled');
            $(this).removeClass('selected');

            if (sliderId === 'copies')
                $('.calc-total-print')
                    .addClass('disabled');
        }
    });

    function validate(input, slider, options)
    {
        let value = input.val(),
            error = 0;

        // if non numeric chars exist in value
        if (value && !/^([0-9]+)$/.test(value))
        {
            let found = value.match(/[0-9]+/g);

            found
                ? value = parseInt(found.join())
                : error++;
        }

        // if value is empty or equal 0 string
        if (!value)
            error++;

        if ((options[0] !== 0) && !parseInt(value))
            error++;

        if (!error) {
            if (value > options[1])
                value = options[1];

            if (value < options[0])
                value = options[0];

            /*if (value % options[2]) {
                value = Math.floor(value / options[2]) + options[2];
            }*/

            input.val(value);
            slider.slider('value', value);
        }

        return error;
    }

    function initDefaultPaper(paperSize)
    {
        let dimensions  = paperSizes[paperSize],
            width       = dimensions[0],
            length      = dimensions[1],
            density     = dimensions[2];

        buttons.removeClass('selected');
        $('#paper_size_' + paperSize).addClass('selected');

        $('#width_value').val(width);
        $('#length_value').val(length);
        $('#density_value').val(density);

        $('#width').slider( 'value', width );
        $('#length').slider( 'value', length );
        $('#density').slider( 'value', density );

        calc(paperSize);
    }

    function calc(paperSize)
    {
        let values = {};
        for (let name in inputs)
            if (inputs.hasOwnProperty(name))
                values[name] = parseInt($('#' + name + '_value').val());

        let weight = (values['length'] * values['width'] * values['density'] * values['quantity'] / (1000 * 1000));

        if (!$('#add_weight').parent('div').hasClass('disabled'))
            weight = weight + values['add_weight'];

        let printWeight = 0;
        if (!$('#copies').parent('div').hasClass('disabled')) {
            printWeight = weight * values['copies'];
            printWeight = getWeight(printWeight);

            $('#print_weight').html(printWeight.weight);
            $('#print_weight_unit').html(printWeight.unit);
        }

        weight      = getWeight(weight);

        $('#weight').html(weight.weight);
        $('#weight_unit').html(weight.unit);

        // draw
        let sheet = $('#sheet'),
            k = 4;
        sheet.css({
            width: (values['width'] / k) + 'px',
            height: (values['length'] / k) + 'px',
        });

        if (paperSize) {
            $('.paper-size-container').removeClass('disabled');
            $('#paper_size').html(paperSize);
        }
    }

    function getWeight(weight)
    {
        let unit = 'g';

        weight = weight.toString();

        let matches = weight.match(/(\d+\.?\d{2}?)/g);

        if (matches && matches.length)
            weight = matches[0];

        let test = weight.split('.')[0].toString(),
            l = test.length;
        if (l > 3) {
            let decimal = weight.slice(l-3, l);
            decimal = Math.round(decimal.slice(0,2) + '.' + decimal[2]);

            weight = weight.slice(0, l-3) + '.' + decimal;
            unit = 'kg';
        }

        return {
            weight: weight,
            unit: unit
        }
    }

    initDefaultPaper('a4');
});