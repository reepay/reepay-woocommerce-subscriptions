import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@woocommerce/blocks-checkout';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useState } from '@wordpress/element';
import metadata from './block.json';
import classnames from 'classnames';
import './assets/style.scss';

// Global import
const { registerCheckoutBlock } = wc.blocksCheckout;
const { has_reepay_subscription, repay_subscription_terms_label } = getSetting( 'wc-reepay-woo-block-terms_data', '' );

const Block = ( props ) => { 
    const { checkoutExtensionData } = props;
    const { setValidationErrors, clearValidationError, getValidationError } = props.validation;
    const validationErrorId = 'reepay_subscription_terms';
    const error = getValidationError( validationErrorId );
	const hasError = error?.hidden === false && error?.message !== '';

    const [agreedToTerms, setAgreedToTerms] = useState(false);

    useEffect(() => {
        checkoutExtensionData.setExtensionData(
			'wc-reepay-woo-block-terms',
			'reepay_subscription_terms',
			agreedToTerms
		);
    }, [agreedToTerms, checkoutExtensionData.setExtensionData]);

    useEffect(() => {
        if( ! has_reepay_subscription ){
            return;
        }

        if ( agreedToTerms ){
            clearValidationError( validationErrorId );
        } else {
            setValidationErrors( {
                [ validationErrorId ]: {
                    message: __(
                        'Please read and accept the subscription terms to proceed',
                        'reepay-subscriptions-for-woocommerce'
                    ),
                    hidden: true,
                },
            } );
        }
        return () => {
			clearValidationError( validationErrorId );
		};
    }, [ agreedToTerms, validationErrorId, clearValidationError, setValidationErrors ]);

    const HasError = () => {
        if ( ! hasError ) return null;
        return (
			<div className="wc-block-components-validation-error" role="alert">
				<p id={ validationErrorId }>
					{ getValidationError( validationErrorId )?.message }
				</p>
			</div>
		);
    }

    if( ! has_reepay_subscription ){
        return <></>
    }

    return (
        <div
			className={ classnames(
				'wc-block-checkout__reepay-subscription-terms wc-block-components-checkbox',
                {
                    'has-error': hasError,
                }
			) }
		>
            <div className="wc-block-components-checkbox">
                <HasError />
                <CheckboxControl
                    className="wc-block-components-checkbox__input"
                    checked={ agreedToTerms }
                    onChange={ setAgreedToTerms }
                    aria-invalid={ hasError === true }
					required={ true }
                >
                    <span
						dangerouslySetInnerHTML={ {
							__html: repay_subscription_terms_label,
						} }
					/>
                </CheckboxControl>
            </div>
        </div>
    )
}

const options = {
	metadata,
	component: Block
};

registerCheckoutBlock( options );
