/*
* http://dev.azartel.ru/serp
* */

jQuery(function ($)
{
    "use strict";

    let descriptionInput    = $('#serp_description'),
        keywordsInput       = $('#serp_keywords'),
        urlInput            = $('#serp_url'),
        titleInput          = $('#serp_title'),
        dateInput           = $('#serp_date');

    let keywords = [];

    let descriptionTouched = 0,
        titleTouched = 0,
        urlTouched = 0;

    descriptionInput.focus(function() {
        if (!descriptionTouched) {
            descriptionTouched = 1;
            descriptionInput.val('');
        }
    });
    titleInput.focus(function() {
        if (!titleTouched) {
            titleTouched = 1;
            titleInput.val('');
        }
    });
    urlInput.focus(function() {
        if (!urlTouched) {
            urlTouched = 1;
            urlInput.val('');
        }
    });

    descriptionInput.keyup(function()
    {
        validateDescription();
    });

    keywordsInput.keyup(function()
    {
        collectKeywords();
        validateDescription();
    });

    urlInput.keyup(function()
    {
        validateUrl();
    });

    titleInput.keyup(function()
    {
        validateTitle();
    });

    dateInput.change(function()
    {
        addDate();
    });

    function addDate()
    {
        let descriptionContainer = $('#serp_google_description'),
            description = descriptionContainer.html();

        if (dateInput.is(':checked')) {

            let months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

            let today   = new Date(),
                dd      = today.getDate(),
                mm      = months[today.getMonth()],
                yyyy    = today.getFullYear();

            let date = '<span id="serp_google_date">' + mm + ' ' + dd + ', ' + yyyy + ' - </span>';

            description = date + description;

            descriptionContainer.html(description);
        } else {
            descriptionContainer
                .find('#serp_google_date')
                .remove();
        }

        validateDescription();
    }

    function validateTitle()
    {
        let string = titleInput.val().trim(),
            titleContainer = $('#serp_google_title'),
            titleCountContainer = $('#serp_title_counter'),
            serpTitleTest = $('#serp_title_test'),
            serpTitleTestContainer = $('.serp-title-test-container');

        titleContainer.html(string);
        serpTitleTest.html(string);

        serpTitleTestContainer.removeClass('d-none');

        let length = serpTitleTest.width();

        serpTitleTestContainer.addClass('d-none');

        titleCountContainer
            .removeClass('serp-error')
            .html(length);

        if (length > 600)
            titleCountContainer
                .addClass('serp-error');

    }

    function validateUrl()
    {
        let string = urlInput.val(),
            urlContainer = $('#serp_google_url');

        urlInput.removeClass('serp-error');

        if (string) {
            urlContainer.html(string.toLowerCase());

            //(/^(?:(?:(?:https?|ftp):)?\/\/)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)(?:\.(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)*(?:\.(?:[a-z\u00a1-\uffff]{2,})))(?::\d{2,5})?(?:[/?#]\S*)?$/i.test(string))
                //? urlContainer.html(string)
                //: urlInput.addClass('serp-error');
        }
    }

    function validateDescription()
    {
        let string = descriptionInput.val().trim(),
            length = string.length,
            descriptionContainer = $('#serp_google_description'),
            descriptionCountContainer = $('#serp_description_counter');

        let left, right, word, index, testString, newString,
            regex = RegExp('[,;!?:\. ]');

        keywords.forEach(function(keyword)
        {
            index = string.toLowerCase().indexOf(keyword.toLowerCase());

            if (index > -1)
            {
                newString = '';

                while (index > -1)
                {
                    left    = string.slice(0, index);
                    right   = string.slice(index + keyword.length, string.length);
                    word    = string.slice(index, index + keyword.length);

                    let leftLastChar, rightFirstChar;
                    if (left)
                        leftLastChar = left[left.length - 1].trim();
                    if (right)
                        rightFirstChar = right[0].trim();

                    (!leftLastChar || regex.test(leftLastChar)) && (!rightFirstChar || regex.test(rightFirstChar))
                        ? newString += left + '<strong>' + word + '</strong>'
                        : newString += left + word;

                    string = right;
                    index = string.toLowerCase().indexOf(keyword);
                }

                string = newString + right;
            }
        });


        let date = descriptionContainer.find('#serp_google_date'),
            dateLength = 0;
        if (date.length === 1)
            dateLength = date.html().length;

        descriptionCountContainer
            .removeClass('serp-error')
            .html(length + dateLength);

        if ( (length + dateLength) > 320) {
            string = string.slice(0, 320 + 1);

            for (let i = string.length; i > 0; i--)
            {
                if (string[i] === ' ') {
                    string = string.slice(0 , i);
                    break;
                }
            }

            string += '...';

            descriptionCountContainer
                .addClass('serp-error');
        }

        if (date.length === 1)
            string = '<span id="serp_google_date">' + date.html() + '</span>' + string;

        descriptionContainer.html(string);
    }

    function collectKeywords()
    {
        let string = keywordsInput.val().trim(),
            kw;

        if (string.length)
        {
            kw = string.split(',');

            keywords = [];
            kw.forEach(function(item) {
                item = item.trim();
                if (item)
                    keywords.push(item);
            });
        }
    }

    collectKeywords();

    if (descriptionInput.val())
        validateDescription();

    if (urlInput.val())
        validateUrl();

    if (titleInput.val())
        validateTitle();

    $('.serp-container').removeClass('v-none');
});