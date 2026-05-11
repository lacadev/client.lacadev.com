const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const fs = require('fs');
const parent = require('./lib/parent-path');

/**
 * Patch sass-loader rules trong default config để thêm @parent alias.
 * Dùng legacy Dart Sass importer (sass-loader v12 + @wordpress/scripts v27).
 */
function patchSassLoader(config) {
  if (!config.module || !config.module.rules) return config;

  const parentResourcesDir = parent.resources();
  const childNodeModulesDir = path.resolve(__dirname, '../../node_modules');

  const patchedRules = config.module.rules.map(rule => {
    if (!rule.use || !Array.isArray(rule.use)) return rule;

    const patchedUse = rule.use.map(loader => {
      const loaderPath = typeof loader === 'string' ? loader : loader?.loader;
      if (!loaderPath || !loaderPath.includes('sass-loader')) return loader;

      const existingOptions = (typeof loader === 'object' ? loader.options : {}) || {};

      return {
        ...(typeof loader === 'object' ? loader : { loader }),
        options: {
          ...existingOptions,
          sassOptions: {
            ...(existingOptions.sassOptions || {}),
            includePaths: [
              childNodeModulesDir,
              parentResourcesDir,
              parent.styles(),
            ],
            // Legacy importer: resolve @parent/* → parent theme resources/*
            importer: function(url) {
              if (url.startsWith('@parent/')) {
                const resolvedPath = url.replace('@parent/', parentResourcesDir + '/');
                const dir  = path.dirname(resolvedPath);
                const base = path.basename(resolvedPath);
                const candidates = [
                  resolvedPath + '.scss',
                  resolvedPath + '.css',
                  path.join(dir, '_' + base + '.scss'),
                  resolvedPath,
                ];
                for (const candidate of candidates) {
                  if (fs.existsSync(candidate)) {
                    return { file: candidate };
                  }
                }
                return { file: resolvedPath };
              }
              return null;
            },
          },
        },
      };
    });

    return { ...rule, use: patchedUse };
  });

  return { ...config, module: { ...config.module, rules: patchedRules } };
}

// === Scan Child-specific blocks ===
const childBlocksDir = path.resolve(__dirname, '../../block-gutenberg');
let childBlockConfigs = [];

if (fs.existsSync(childBlocksDir)) {
  const blocks = fs.readdirSync(childBlocksDir).filter(dir =>
    fs.statSync(path.join(childBlocksDir, dir)).isDirectory() &&
    fs.existsSync(path.join(childBlocksDir, dir, 'block.json'))
  );

  childBlockConfigs = blocks.map(block =>
    patchSassLoader({
      ...defaultConfig,
      entry: {
        index: path.join(childBlocksDir, block, 'index.js'),
      },
      output: {
        ...defaultConfig.output,
        path:     path.join(childBlocksDir, block, 'build'),
        filename: '[name].js',
      },
    })
  );
}

// === Global Gutenberg bundle: Parent theme blocks → dist/gutenberg/ of Child ===
const gutenbergLegacyConfig = patchSassLoader({
  ...defaultConfig,
  entry: {
    index: path.join(parent.root('block-gutenberg'), 'index.js'),
  },
  output: {
    ...defaultConfig.output,
    path:     path.resolve(__dirname, '../../dist/gutenberg'),
    filename: '[name].js',
  },
  resolve: {
    ...defaultConfig.resolve,
    modules: [
      ...(defaultConfig.resolve?.modules || ['node_modules']),
      path.resolve(__dirname, '../../node_modules'),
    ],
  },
});

module.exports = [...childBlockConfigs, gutenbergLegacyConfig];
