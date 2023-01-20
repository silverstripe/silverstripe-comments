const Path = require('path');
const { JavascriptWebpackConfig, CssWebpackConfig } = require('@silverstripe/webpack-config');
const CopyWebpackPlugin = require('copy-webpack-plugin');

const ENV = process.env.NODE_ENV;
const PATHS = {
    MODULES: 'node_modules',
    ROOT: Path.resolve(),
    SRC: Path.resolve('client/src'),
    DIST: Path.resolve('client/dist'),
    DIST_JS: Path.resolve('client/dist/js'),
    LEGACY_SRC: Path.resolve('client/src/legacy'),
};

const jsConfig = new JavascriptWebpackConfig('js', PATHS, 'silverstripe/comments')
  .setEntry({
    CommentsInterface: `${PATHS.LEGACY_SRC}/CommentsInterface.js`,
  })
  .mergeConfig({
    plugins: [
      new CopyWebpackPlugin({
        patterns: [
          {
              from: `${PATHS.MODULES}/jquery/dist/jquery.min.js`,
              to: PATHS.DIST_JS
          },
          {
            context: `${PATHS.MODULES}/jquery-validation/dist`,
            from: '**/*.min.js',
            to: `${PATHS.DIST_JS}/jquery-validation/`
          },
        ],
      }),
    ],
  })
  .getConfig();

// Don't apply any externals, as this js will be used on the front-end.
jsConfig.externals = {};

const config = [
  // Main JS bundle
  jsConfig,
  // sass to css
  new CssWebpackConfig('css', PATHS)
    .setEntry({
      comments: `${PATHS.SRC}/styles/comments.scss`,
      cms: `${PATHS.SRC}/styles/cms.scss`,
    })
    .getConfig(),
];

// Use WEBPACK_CHILD=js or WEBPACK_CHILD=css env var to run a single config
module.exports = (process.env.WEBPACK_CHILD)
    ? config.find((entry) => entry.name === process.env.WEBPACK_CHILD)
    : config;
