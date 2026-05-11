/**
 * The internal dependencies.
 */
const utils = require('../lib/utils');
const parent = require('../lib/parent-path');
const path = require('path');

// Entry points từ parent theme (theme/admin/login/editor đều dùng parent source)
const parentSrc = parent.scripts();
const childSrc  = path.resolve(__dirname, '../../scripts');

module.exports = {
  'theme':  path.join(parentSrc, 'theme/index.js'),
  'admin':  path.join(parentSrc, 'admin/index.js'),
  'login':  path.join(parentSrc, 'login/index.js'),
  'editor': path.join(parentSrc, 'editor/index.js'),
  // Child theme: SCSS override + custom JS
  // Dùng array entry để webpack bundle cả 2 vào dist/child.js + dist/styles/child.css
  'child': [
    utils.srcStylesPath('child.scss'),
    path.join(childSrc, 'theme/index.js'),
  ],
};
