const path = require('path');

module.exports = (env, argv) => {
  const isDevelopment = argv.mode === 'development';
  
  return {
    entry: './frontend/src/index.js',
    output: {
      path: path.resolve(__dirname, 'assets/js/dist'),
      filename: isDevelopment ? 'bundle.js' : 'bundle.[contenthash].js',
      publicPath: '/wp-content/plugins/goodereader-bookrec/assets/js/dist/',
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env', '@babel/preset-react'],
              // Add this for production builds to optimize React
              plugins: !isDevelopment ? [
                '@babel/plugin-transform-react-constant-elements',
                '@babel/plugin-transform-react-inline-elements'
              ] : []
            },
          },
        },
        {
          test: /\.css$/,
          use: [
            'style-loader',
            {
              loader: 'css-loader',
              options: {
                importLoaders: 1,
              }
            },
            {
              loader: 'postcss-loader',
              options: {
                postcssOptions: {
                  plugins: [
                    'tailwindcss',
                    'autoprefixer',
                    // Optimize CSS in production
                    ...(!isDevelopment ? ['cssnano'] : []),
                  ],
                },
              },
            },
          ],
        },
      ],
    },
    resolve: {
      extensions: ['.js', '.jsx'],
    },
    devtool: isDevelopment ? 'source-map' : false,
    // Enable tree-shaking and code-splitting
    optimization: {
      usedExports: true,
      minimize: !isDevelopment,
      splitChunks: {
        chunks: 'all',
        name: false,
      },
    },
    // Add some performance hints in production
    performance: {
      hints: isDevelopment ? false : 'warning',
      maxAssetSize: 250000, // 250 KB
      maxEntrypointSize: 400000, // 400 KB
    },
  };
};