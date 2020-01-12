/*
* http://dev.azartel.ru/significance-calc
* */

jQuery(function ($)
{
    "use strict";

    calc();

    $('.calc-audience, .calc-converted').keyup(function()
    {
        let input = $(this),
            value = validateCalcInput(input);

        input.val(value);

        calc();
    });

    function validateCalcInput(input)
    {
        let value = input.val();

        // if non numeric chars exist in value
        if (value && !/^([0-9]+)$/.test(value))
        {
            let found = value.match(/[0-9]+/g);

            found
                ? value = parseInt(found.join())
                : value = '';
        }

        if (input.hasClass('calc-audience'))
            if (value === '0')
                value = 1;

        return value;
    }

    function calc()
    {
        let filled = 1;
        $('.calc-input').each(function(key, input)
        {
            if ($(input).val() === '')
                filled = 0;
        });

        let convertedAInput = $('#converted_a'),
            convertedBInput = $('#converted_b');

        let convertedA = convertedAInput.val(),
            convertedB = convertedBInput.val(),
            audienceA = $('#audience_a').val(),
            audienceB = $('#audience_b').val();

        let validA = renderConversion(convertedA, audienceA, 'a'),
            validB = renderConversion(convertedB, audienceB, 'b');

        convertedAInput.removeClass('calc-error');
        if (!validA)
            convertedAInput.addClass('calc-error');

        convertedBInput.removeClass('calc-error');
        if (!validB)
            convertedBInput.addClass('calc-error');

        $('.a-block, .b-block').removeClass('best-conversion');

        if (filled && validA && validB)
        {
            convertedA = parseInt(convertedA);
            convertedB = parseInt(convertedB);
            audienceA = parseInt(audienceA);
            audienceB = parseInt(audienceB);

            let data = calcData(convertedA, convertedB, audienceA, audienceB),
                pValue = data.pValue,
                crA = data.crA,
                crB = data.crB,
                equal = data.equal;

            // computing confidence
            let from = 0,
                to = 10000,
                i = 0,
                limit = powsOfTwo(to), // = 14
                middle, s, c;
            while( (to - from > 1) && (i < limit)) // not more than 2^14 (2^14 > 10000)
            {
                middle = Math.floor((to + from) / 2);
                c = middle / 10000;
                s = (pValue > (c + (1-c)/2)) || (pValue < (1 - c - (1-c)/2));

                s
                    ? from = middle
                    : to = middle;

                i++;
            }

            let confidence = c * 100,
                confidenceLevel = null,
                convertedExtra = null;

            if ( (confidence < 99) && (!equal) ) {
                confidenceLevel = 99;
                if (confidence < 95)
                    confidenceLevel = 95;

                c = confidenceLevel / 100;

                if (crA > crB) {

                    let from = convertedA,
                        to = audienceA,
                        i = 0,
                        limit = powsOfTwo(to) + 1,
                        middle, s;

                    while( (to - from > 1) && (i < limit) )
                    {
                        middle = Math.floor((to + from) / 2);

                        s = calcConvertedExtra(middle, convertedB, audienceA, audienceB, c);

                        s ? to = middle : from = middle;

                        i++;
                    }

                    convertedExtra = to - convertedA;

                } else {

                    let from = convertedB,
                        to = audienceB,
                        i = 0,
                        limit = powsOfTwo(to) + 1,
                        middle, s;

                    while( (to - from > 1) && (i < limit) )
                    {
                        middle = Math.floor((to + from) / 2);

                        s = calcConvertedExtra(convertedA, middle, audienceA, audienceB, c);

                        s ? to = middle : from = middle;

                        i++;
                    }

                    convertedExtra = to - convertedB;
                }
            }

            // difference
            let best = null;
            if (crA !== crB)
                best = (crA > crB) ? 'a' : 'b';

            let diff = 0;
            if (crA && crB)
                diff = (crA > crB) ? (crA / crB) * 100 - 100 : (crB / crA) * 100 - 100;
            else if (crA || crB)
                diff = 100;

            render(best, diff, confidence, convertedExtra, confidenceLevel, equal);

            $('.calc-results').removeClass('d-none');
        } else {
            $('.calc-results').addClass('d-none');
        }
    }

    function calcConvertedExtra(convertedA, convertedB, audienceA, audienceB, c)
    {
        let data = calcData(convertedA, convertedB, audienceA, audienceB),
            pValue = data.pValue;

        return (pValue > (c + (1-c)/2)) || (pValue < (1 - c - (1-c)/2));
    }

    function calcData(convertedA, convertedB, audienceA, audienceB)
    {
        // conversions
        let crA = convertedA / audienceA,
            crB = convertedB / audienceB;

        // standard errors
        let seA     = Math.sqrt(crA * (1 - crA) / audienceA),
            seB     = Math.sqrt(crB * (1 - crB) / audienceB),
            stErD   = Math.sqrt(Math.pow(seA, 2) + Math.pow(seB, 2));

        let zScore = 0,
            equal = 0;

        (crA !== crB)
            ? zScore = (crB - crA) / stErD
            : equal = 1;

        let pValue = (1 - poz(zScore));

        return {
            crA:    crA,
            crB:    crB,
            stErD:  stErD,
            zScore: zScore,
            pValue: pValue,
            equal:  equal
        };
    }

    function render(best, diff, confidence, convertedExtra, confidenceLevel, equal)
    {
        let confidenceBig = confidence.toFixed(1) + '%',
            confidenceSmall = confidence.toFixed(2) + '%',
            diffSmall = diff.toFixed(2) + '%',
            bestLetter = best ? best.toUpperCase() : '&mdash;';

        let conversionDesc =
            'Variant <strong>' + bestLetter + '</strong> converted ' +
            '<strong>' + diffSmall + '</strong> better.',
            confidenceDesc =
                'We are <strong>' + confidenceSmall + '</strong> confident that the changes ' +
                'in Variant <strong>' + bestLetter + '</strong> will improve your conversion rate. ';

        if (!best) {
            conversionDesc = 'Both variants are <strong>equal</strong> conversion.';
            confidenceDesc = 'We are confident that you need to collect more data to compare conversions.';
        }

        let significanceDesc = 'This is a <strong>sufficient</strong> rate of statistical significance.';
        if (confidence > 99)
            significanceDesc = 'This is a <strong>fantastic</strong> rate of statistical significance.';
        
        if ( (confidenceLevel === 95) || equal)
            significanceDesc = 'This is an <strong>insufficient</strong> rate of statistical significance.';

        if (convertedExtra) {

            confidenceLevel = confidenceLevel.toFixed(0) + '%';

            significanceDesc +=
                '</br>You need an extra <strong>' + convertedExtra + '</strong> ' +
                'in Variant <strong>' + bestLetter + '</strong> ' +
                'for getting <strong>' + confidenceLevel + '</strong> confidence level.';
        }

        $('#conversion_value').html(bestLetter);
        $('#conversion_desc').html(conversionDesc);
        $('#confidence_value').html(confidenceBig);
        $('#confidence_desc').html(confidenceDesc);
        $('#significance_desc').html(significanceDesc);

        $('.a-block, .b-block').removeClass('best-conversion');
        $('.' + best + '-block').addClass('best-conversion');
    }

    function renderConversion(converted, audience, letter)
    {
        let html = '&mdash;',
            valid = 1;
        if ( (converted !== '') && (audience !== '') )
        {
            converted = parseInt(converted);
            audience = parseInt(audience);

            html = ((converted / audience) * 100).toFixed(2) + '%';

            valid = (converted / audience <= 1);
        }

        $('#conversion_' + letter).html(html);

        return valid;
    }

    function powsOfTwo(number)
    {
        let pow = 0,
            i = 1;
        while(i < number)
        {
            i = i * 2;
            pow++;
        }

        return pow;
    }

    function poz(z)
    {
        let y, x, w;
        let Z_MAX = 6;

        if (z === 0.0) {
            x = 0.0;
        } else {
            y = 0.5 * Math.abs(z);
            if (y > (Z_MAX * 0.5)) {
                x = 1.0;
            } else if (y < 1.0) {
                w = y * y;
                x = ((((((((0.000124818987 * w
                    - 0.001075204047) * w + 0.005198775019) * w
                    - 0.019198292004) * w + 0.059054035642) * w
                    - 0.151968751364) * w + 0.319152932694) * w
                    - 0.531923007300) * w + 0.797884560593) * y * 2.0;
            } else {
                y -= 2.0;
                x = (((((((((((((-0.000045255659 * y
                    + 0.000152529290) * y - 0.000019538132) * y
                    - 0.000676904986) * y + 0.001390604284) * y
                    - 0.000794620820) * y - 0.002034254874) * y
                    + 0.006549791214) * y - 0.010557625006) * y
                    + 0.011630447319) * y - 0.009279453341) * y
                    + 0.005353579108) * y - 0.002141268741) * y
                    + 0.000535310849) * y + 0.999936657524;
            }
        }
        return z > 0.0 ? ((x + 1.0) * 0.5) : ((1.0 - x) * 0.5);
    }
});