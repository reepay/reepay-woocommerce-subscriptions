import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { CheckboxControl } from '@woocommerce/blocks-checkout';
import { getSetting } from '@woocommerce/settings';
import './assets/style.scss';

const { repay_subscription_terms_label } = getSetting( 'wc-reepay-woo-block-terms_data', '' );

export const Edit = ({ attributes, setAttributes }) => {
	const blockProps = useBlockProps();
    const { agreedToTerms } = attributes;

	return (
		<div {...blockProps}>
			<div className={ 'wc-block-checkout__reepay-subscription-terms' }> 
                <div className="wc-block-components-checkbox">
                    <CheckboxControl
                        className="wc-block-components-checkbox__input"
                        checked={ agreedToTerms }
                        onChange={ ( newValue ) => setAttributes({ agreedToTerms: newValue }) }
                    >
                        <span
                            dangerouslySetInnerHTML={ {
                                __html: repay_subscription_terms_label,
                            } }
                        />
                    </CheckboxControl>
                </div>
			</div>
		</div>
	);
};

export const Save = () => {
	return (
		<div { ...useBlockProps.save() }>
			<InnerBlocks.Content />
		</div>
	);
};
