/**
 * The internal dependencies.
 */
const utils = require('../lib/utils');
const path = require('path');

// Trỏ trực tiếp source scripts về parent theme
const parentSrc    = path.resolve(__dirname, '../../../../lacadev-client/resources/scripts');
const parentStyles = path.resolve(__dirname, '../../../../lacadev-client/resources/styles');
const childSrc     = path.resolve(__dirname, '../../scripts');

const isParentTarget = process.env.LACA_BUILD_TARGET === 'parent';

const parentEntries = {
  'admin': path.join(parentSrc, 'admin/index.js'),
  'login': path.join(parentSrc, 'login/index.js'),
  'editor': path.join(parentSrc, 'editor/index.js'),
  'laca-seo': [
    path.join(parentSrc, 'admin/laca-seo/index.js'),
    path.join(parentStyles, 'admin/laca-seo/index.scss'),
  ],
  'laca-seo-editor': [
    path.join(parentSrc, 'admin/laca-seo/editor.js'),
    path.join(parentStyles, 'admin/laca-seo/editor.scss'),
  ],
};

const childEntries = {
  'theme': path.join(parentSrc, 'theme/index.js'),
  'admin': path.join(parentSrc, 'admin/index.js'),
  'login': path.join(parentSrc, 'login/index.js'),
  // 'editor' vốn chỉ trỏ vào parent — bundle thêm fonts.scss của
  // child để trang soạn thảo Gutenberg cũng load được các
  // font riêng của site, không chỉ frontend. Cùng pattern với entry 'child'.
  'editor': [
    path.join(parentSrc, 'editor/index.js'),
    utils.srcStylesPath('theme/base/_fonts.scss'),
  ],
  // Child theme: SCSS override + custom JS (archive-gallery, etc.)
  // Dùng array entry để webpack bundle cả 2 vào dist/child.js + dist/styles/child.css
  'child': [
    utils.srcStylesPath('child.scss'),
    path.join(childSrc, 'theme/index.js'),
  ],
  // Laca SEO Admin Bundle (Phase 0 & 1) — giữ 1 bản phát hành chuyển tiếp, gỡ ở 4.0
  'laca-seo': [
    path.join(parentSrc, 'admin/laca-seo/index.js'),
    path.join(parentStyles, 'admin/laca-seo/index.scss'),
  ],
  // Laca SEO Editor Bundle (Phase 2) — giữ 1 bản phát hành chuyển tiếp, gỡ ở 4.0
  'laca-seo-editor': [
    path.join(parentSrc, 'admin/laca-seo/editor.js'),
    path.join(parentStyles, 'admin/laca-seo/editor.scss'),
  ],
};

module.exports = isParentTarget ? parentEntries : childEntries;
