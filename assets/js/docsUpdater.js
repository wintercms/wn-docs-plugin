/*
 * Docs Updater class
 *
 * Dependences:
 * - Waterfall plugin (waterfall.js)
 */

+function ($) { "use strict";

    var DocsUpdater = function () {

        // Init
        this.init()
    }

    DocsUpdater.prototype.init = function() {
        this.activeStep = null;
        this.updateSteps = null;
    };

    DocsUpdater.prototype.execute = function(steps) {
        this.updateSteps = steps;
        this.runUpdate();
    };

    DocsUpdater.prototype.runUpdate = function (fromStep) {
        $.waterfall
            .apply(this, this.buildEventChain(this.updateSteps, fromStep))
            .fail(function (reason) {
                var template = $('#executeFailed').html(),
                    html = Mustache.to_html(template, { reason: reason });

                $('#executeActivity').hide();
                $('#executeStatus').html(html);
            });
    };

    DocsUpdater.prototype.retryUpdate = function () {
        $('#executeActivity').show();
        $('#executeStatus').html('');

        this.runUpdate(this.activeStep);
    };

    DocsUpdater.prototype.buildEventChain = function (steps, fromStep) {
        var self = this,
            eventChain = [],
            skipStep = fromStep ? true : false;

        $.each(steps, function (index, step) {
            if (step == fromStep) {
                skipStep = false;
            }

            if (skipStep) {
                return true; // Continue
            }

            eventChain.push(function(){
                var deferred = $.Deferred()

                self.activeStep = step
                self.setLoadingBar(true, step.label)

                $.request('onExecuteStep', {
                    data: step,
                    success: function (data) {
                        setTimeout(function() { deferred.resolve(); }, 600);

                        if (step.code == 'completeUpdate' || step.code == 'completeInstall') {
                            this.success(data);
                        } else {
                            self.setLoadingBar(false);
                        }
                    },
                    error: function (data) {
                        self.setLoadingBar(false);
                        deferred.reject(data.responseText);
                    }
                });

                return deferred;
            });
        });

        return eventChain;
    };

    DocsUpdater.prototype.setLoadingBar = function (state, message) {
        var loadingBar = $('#executeLoadingBar'),
            messageDiv = $('#executeMessage');

        if (state) {
            loadingBar.removeClass('bar-loaded');
        } else {
            loadingBar.addClass('bar-loaded');
        }

        if (message) {
            messageDiv.text(message);
        }
    };

    if ($.wn === undefined) {
        $.wn = {};
    }
    if ($.oc === undefined) {
        $.oc = $.wn;
    }

    $.wn.docsUpdater = new DocsUpdater();

}(window.jQuery);
