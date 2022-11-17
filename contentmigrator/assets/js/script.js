"use strict";

// ############################################################################
// WINDOW LOAD
// ############################################################################

window.addEventListener('load', function() {

    // ++++++++++++++++++++++++
    // IMAGE IMPORT
    var startImport = document.getElementsByClassName('js-startImport');

    Array.prototype.forEach.call(startImport, function(element) {
        element.addEventListener('click', function(event) {

            event.preventDefault();
            console.log("1. startImport clicked");

            var imagesToImport = document.getElementsByClassName('js-imageToImport');
            Array.prototype.forEach.call(imagesToImport, function(element) {

                console.log("2. forEach imageToImport");

                funcImportImages(element);

            });

        });
    });

});


// ############################################################################
// FUNCTIONS
// ############################################################################

// funcImportImages
var funcImportImages = function(element) {

    console.log("3. funcImportImages");
    //console.log(element);
    console.log(element.getAttribute('data-imagename'));

    var importContainer = document.getElementById('fr-importContainer');

    let imageImportXHR = new XMLHttpRequest();
    imageImportXHR.onreadystatechange = function() {
        console.log("5.");
        if (this.readyState === 4) {
            if (this.status === 200) {
                console.log("6.1");
                importContainer.innerHTML = '';		// clear any previously loaded data
                var template = document.createElement('div');
                template.innerHTML = this.responseText;
                importContainer.appendChild(template);
                //element.appendChild(template);
                element.querySelector('a').style.color = "#3bb594";
            }
            else {
                console.log("6.2");
                var error = document.createElement('p');
                error.innerHTML = 'Fehler beim Laden des Images.';
                importContainer.appendChild(error);
                //element.appendChild(error);
                element.querySelector('a').style.color = "red";
            }
        }
        else {
            importContainer.innerHTML = '<div class="loadingAnimation"></div>';
        }
    };
    var importImageName = element.getAttribute('data-imagename');
    var targetCat = document.getElementById('targetCat').value;

    imageImportXHR.open('GET','index.php?rex-api-call=contentmigrator&image_name='+importImageName+'&mediapool_category='+targetCat,true);
    imageImportXHR.send();
};

