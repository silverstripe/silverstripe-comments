const Path = require('path');
const dir = require('node-dir');
const webpackConfig = require('@silverstripe/webpack-config');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const {
  resolveJS,
  externalJS,
  moduleJS,
  pluginJS,
  moduleCSS,
  pluginCSS,
} = webpackConfig;

const ENV = process.env.NODE_ENV;
const PATHS = {
    MODULES: 'node_modules',
    MODULES_ABS: Path.resolve('node_modules'),
    FILES_PATH: '../',
    ROOT: Path.resolve(),
    SRC: Path.resolve('client/src'),
    DIST: Path.resolve('client/dist'),
    DIST_JS: Path.resolve('client/dist/js'),
    LEGACY_SRC: Path.resolve('client/src/legacy'),
};

const copyData = [
  {
      from: PATHS.MODULES + '/jquery/dist/jquery.min.js',
      to: PATHS.DIST_JS
  },
];

/**
 * Builds a list of files matching the `*.min.js` pattern to copy from a source
 * directory to a dist directory.
 */
const addMinFiles = (from, to) => {
  const sourceDir = PATHS.MODULES_ABS + from;
  dir.files(sourceDir, (err, files) => {
    if (err) throw err;
    files.forEach(file => {
      filename = file.replace(sourceDir, '');
      if (!filename.match(/\.min\.js$/)) {
        return;
      }
      copyData.push({
        from: PATHS.MODULES + from + filename,
        to: PATHS.DIST_JS + to + filename
      })
    });
  });
};

addMinFiles('/jquery-validation/dist', '/jquery-validation');

const config = [
    {
        name: 'js',
        entry: {
            CommentsInterface: `${PATHS.LEGACY_SRC}/CommentsInterface.js`,
        },
        output: {
            path: PATHS.DIST,
            filename: 'js/[name].js',
        },
        devtool: (ENV !== 'production') ? 'source-map' : '',
        resolve: resolveJS(ENV, PATHS),
        externals: externalJS(ENV, PATHS),
        module: moduleJS(ENV, PATHS),
        plugins: pluginJS(ENV, PATHS).concat([
          new CopyWebpackPlugin(copyData)
        ])
    },
    {
        name: 'css',
        entry: {
            comments: `${PATHS.SRC}/styles/comments.scss`,
            cms: `${PATHS.SRC}/styles/cms.scss`,
        },
        output: {
            path: PATHS.DIST,
            filename: 'styles/[name].css',
        },
        devtool: (ENV !== 'production') ? 'source-map' : '',
        module: moduleCSS(ENV, PATHS),
        plugins: pluginCSS(ENV, PATHS),
    },
];

// Use WEBPACK_CHILD=js or WEBPACK_CHILD=css env var to run a single config
module.exports = (process.env.WEBPACK_CHILD)
    ? config.find((entry) => entry.name === process.env.WEBPACK_CHILD)
    : module.exports = config;
