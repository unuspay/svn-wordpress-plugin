(function ( ) {

jQuery(function ($) {
    $(document).ajaxError(function () {
        if (typeof window._depayUnmountLoading == "function") {
            window._depayUnmountLoading();
        }
    });

    $(document).ajaxComplete(function () {
        if (typeof window._depayUnmountLoading == "function") {
            window._depayUnmountLoading();
        }
    });

    $("form.edd-blocks-form__purchase").on("submit", async () => {
        var values = $("form.edd-blocks-form__purchase").serialize();
        
        if (values.match("edd-gateway=edd_unuspay_gateway")) {
            console.log("values",values);
            let { unmount } = await UnusPayWidgets.Loading({
                text: "Loading payment data...",
            });
            setTimeout(unmount, 10000);
        }
    });
});


const displayCheckout = async () => {
    if (window.location.hash.startsWith("#edd-unuspay-checkout-")) {
        const checkoutId = window.location.hash.match(
            /edd-unuspay-checkout-(.*?)@/
        )[1];
        const response = JSON.parse(
            await wp.apiRequest({
                path: `/unuspay/edd/checkouts/${checkoutId}`,
                method: "POST",
            })
        );
        if (response.redirect) {
            window.location = response.redirect;
            return;
        }
        const paymentInfo = [];
        response.tokens.forEach((token) => {
            paymentInfo.push({
                blockchain: token.blockchain,
                amount: token.amount,
                token: token.tokenAddress,
                receiver: token.receiveAddress,
                fee: {
                    amount: token.feeRate + "%",
                    receiver: token.feeAddress,
                },
            });
        });
        let configuration = {
            accept: paymentInfo,
            closed: () => {
                window.location.hash = "";
                window.location.reload(true);
            },
            track: {
                id: checkoutId,
                endpoint: "/wp-json/unuspay/edd/track",
                poll: {
                    endpoint: "/wp-json/unuspay/edd/release"
                }

            },
             style:{colors:{}},
             
        };

        if(response.colorBody && response.colorBody.colorSwitch){
             
            if(response.colorBody.buttonColor&&response.colorBody.buttonColor!=""){
                configuration.style.colors.primary=response.colorBody.buttonColor;
            }
            if(response.colorBody.buttonTextColor&&response.colorBody.buttonTextColor!=""){
                configuration.style.colors.buttonText=response.colorBody.buttonTextColor;
            }
             if(response.colorBody.buttonFont&&response.colorBody.buttonFont!=""){
                configuration.style.fontFamily=response.colorBody.buttonFont;
            }
        }

        UnusPayWidgets.Payment(configuration);
    }
};

document.addEventListener('DOMContentLoaded', displayCheckout);
window.addEventListener('hashchange', displayCheckout);

})()
