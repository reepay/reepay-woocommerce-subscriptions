(()=>{"use strict";var e,t={724:()=>{const e=window.React,t=window.wp.blocks,o=window.wp.element,r=(0,o.forwardRef)((function({icon:e,size:t=24,...r},c){return(0,o.cloneElement)(e,{width:t,height:t,...r,ref:c})})),c=window.wp.primitives,s=(0,e.createElement)(c.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24"},(0,e.createElement)(c.Path,{d:"M4 20h9v-1.5H4V20zm0-5.5V16h16v-1.5H4zm.8-4l.7.7 2-2V12h1V9.2l2 2 .7-.7-2-2H12v-1H9.2l2-2-.7-.7-2 2V4h-1v2.8l-2-2-.7.7 2 2H4v1h2.8l-2 2z"})),n=JSON.parse('{"apiVersion":3,"name":"wc-reepay-woo-blocks-terms/checkbox","version":"1.0.0","title":"Subscription terms checkbox","description":"The subscription terms checkbox","parent":["woocommerce/checkout-fields-block"],"category":"woocommerce","supports":{"html":false,"align":false,"multiple":false,"reusable":false},"attributes":{"lock":{"type":"object","default":{"remove":true,"move":true}}},"textdomain":"reepay-subscriptions-for-woocommerce","editorScript":"file:./build/index.js","editorStyle":"file:./build/style-index.css"}'),i=window.wp.blockEditor,l=window.wc.blocksCheckout,a=window.wc.wcSettings,{repay_subscription_terms_label:m}=(0,a.getSetting)("wc-reepay-woo-block-terms_data","");(0,t.registerBlockType)(n,{icon:{src:(0,e.createElement)(r,{icon:s,className:"wc-block-editor-components-block-icon"})},edit:({attributes:t,setAttributes:o})=>{const r=(0,i.useBlockProps)(),{agreedToTerms:c}=t;return(0,e.createElement)("div",{...r},(0,e.createElement)("div",{className:"wc-block-checkout__reepay-subscription-terms"},(0,e.createElement)("div",{className:"wc-block-components-checkbox"},(0,e.createElement)(l.CheckboxControl,{className:"wc-block-components-checkbox__input",checked:c,onChange:e=>o({agreedToTerms:e})},(0,e.createElement)("span",{dangerouslySetInnerHTML:{__html:m}})))))},save:()=>(0,e.createElement)("div",{...i.useBlockProps.save()},(0,e.createElement)(i.InnerBlocks.Content,null))})}},o={};function r(e){var c=o[e];if(void 0!==c)return c.exports;var s=o[e]={exports:{}};return t[e](s,s.exports,r),s.exports}r.m=t,e=[],r.O=(t,o,c,s)=>{if(!o){var n=1/0;for(m=0;m<e.length;m++){o=e[m][0],c=e[m][1],s=e[m][2];for(var i=!0,l=0;l<o.length;l++)(!1&s||n>=s)&&Object.keys(r.O).every((e=>r.O[e](o[l])))?o.splice(l--,1):(i=!1,s<n&&(n=s));if(i){e.splice(m--,1);var a=c();void 0!==a&&(t=a)}}return t}s=s||0;for(var m=e.length;m>0&&e[m-1][2]>s;m--)e[m]=e[m-1];e[m]=[o,c,s]},r.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={57:0,350:0};r.O.j=t=>0===e[t];var t=(t,o)=>{var c,s,n=o[0],i=o[1],l=o[2],a=0;if(n.some((t=>0!==e[t]))){for(c in i)r.o(i,c)&&(r.m[c]=i[c]);if(l)var m=l(r)}for(t&&t(o);a<n.length;a++)s=n[a],r.o(e,s)&&e[s]&&e[s][0](),e[s]=0;return r.O(m)},o=self.webpackChunkcheckout_block_subscription_terms=self.webpackChunkcheckout_block_subscription_terms||[];o.forEach(t.bind(null,0)),o.push=t.bind(null,o.push.bind(o))})();var c=r.O(void 0,[350],(()=>r(724)));c=r.O(c)})();