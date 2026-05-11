/**
 * The internal dependencies.
 */
const utils = require('../lib/utils');
const parent = require('../lib/parent-path');
const path = require('path');

module.exports = {
  modules: [
    utils.themeRootPath('node_modules'),
    utils.srcScriptsPath(),
    parent.nodeModules(),
    'node_modules'
  ],
  extensions: ['.js', '.jsx', '.json', '.css', '.scss'],
  alias: {
    // config.json BẮT BUỘC dùng của Child Theme (BrowserSync URL + color overrides)
    '@config':      utils.themeRootPath('config.json'),
    '@scripts':     parent.scripts(),
    '@styles':      parent.styles(),
    '@images':      parent.resources('images'),
    '@fonts':       parent.resources('fonts'),
    '@child-fonts': path.resolve(__dirname, '../../fonts'),
    '@vendor':      parent.resources('vendor'),
    // Phục vụ cho output của child theme
    '@dist':        utils.distPath(),
    '@child':       utils.srcPath(),
    '@parent':      parent.resources(),
    '~':            utils.themeRootPath('node_modules'),
    'isotope':      'isotope-layout',
  },
};
