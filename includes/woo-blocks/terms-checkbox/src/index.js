import { registerBlockType } from '@wordpress/blocks';
import { Icon, customPostType } from '@wordpress/icons';
import metadata from './block.json';
import { Edit, Save } from './edit';

registerBlockType(metadata, {
	icon: {
        src: (
            <Icon
                icon={ customPostType }
                className="wc-block-editor-components-block-icon"
            />
        ),
    },
	edit: Edit,
    save: Save,
});
