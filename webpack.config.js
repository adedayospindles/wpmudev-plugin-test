/**
 * Webpack configuration for WPMU DEV plugin
 * Handles JS/React compilation, CSS extraction, RTL, and asset dependency generation
 */

const path = require("path");
const glob = require("glob");
const TerserPlugin = require("terser-webpack-plugin");
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin");
const { CleanWebpackPlugin } = require("clean-webpack-plugin");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const { PurgeCSSPlugin } = require("purgecss-webpack-plugin");
const RTLPlugin = require("rtlcss-webpack-plugin");
const DependencyExtractionWebpackPlugin = require("@wordpress/dependency-extraction-webpack-plugin");
const defaultConfig = require("@wordpress/scripts/config/webpack.config");

// -----------------------------------------------------------------------------
// Define paths for PurgeCSS scanning
// -----------------------------------------------------------------------------
const PATHS = {
	src: path.join(__dirname, "src"),
	app: path.join(__dirname, "app"),
	core: path.join(__dirname, "core"),
};

// -----------------------------------------------------------------------------
// Export Webpack configuration
// -----------------------------------------------------------------------------
module.exports = {
	...defaultConfig,

	// -------------------------------------------------------------------------
	// Entry points for your admin pages
	// -------------------------------------------------------------------------
	entry: {
		drivetestpage: "./src/googledrive-page/main.jsx",
		postsmaintenancepage: "./src/posts-maintenance/main.jsx",
	},

	// -------------------------------------------------------------------------
	// Output settings
	// -------------------------------------------------------------------------
	output: {
		path: path.resolve(__dirname, "assets/js"),
		filename: "[name].min.js",
		publicPath: "../../",
		assetModuleFilename: "images/[name][ext][query]",
		clean: true,
	},

	// -------------------------------------------------------------------------
	// Module resolution
	// -------------------------------------------------------------------------
	resolve: {
		extensions: [".js", ".jsx"], // Auto-resolve JS and JSX files
	},

	// -------------------------------------------------------------------------
	// Module rules: JS, CSS/SCSS, images, fonts
	// -------------------------------------------------------------------------
	module: {
		...defaultConfig.module,
		rules: [
			// JS / JSX transpilation
			{
				test: /\.(js|jsx)$/,
				exclude: /node_modules/,
				use: "babel-loader",
			},

			// CSS / SCSS handling
			{
				test: /\.(css|scss)$/,
				exclude: /node_modules/,
				use: [
					MiniCssExtractPlugin.loader,
					"css-loader",
					{
						loader: "sass-loader",
						options: { sassOptions: { outputStyle: "compressed" } },
					},
				],
			},

			// Images
			{
				test: /\.(png|jpg|gif|svg)$/,
				type: "asset/resource",
				generator: { filename: "../images/[name][ext][query]" },
			},

			// Fonts
			{
				test: /\.(woff|woff2|eot|ttf|otf)$/,
				type: "asset/resource",
				generator: { filename: "../fonts/[name][ext][query]" },
			},
		],
	},

	// -------------------------------------------------------------------------
	// WordPress externals
	// -------------------------------------------------------------------------
	externals: {
		react: "React",
		"react-dom": "ReactDOM",
		"@wordpress/element": "wp.element",
		"@wordpress/i18n": "wp.i18n",
		"@wordpress/components": "wp.components",
		"@wordpress/api-fetch": "wp.apiFetch",
	},

	// -------------------------------------------------------------------------
	// Plugins
	// -------------------------------------------------------------------------
	plugins: [
		// 1. Extract WP dependencies to separate scripts
		new DependencyExtractionWebpackPlugin({
			injectPolyfill: true,
			requestToExternal: (request) => {
				if (request.startsWith("@wordpress/")) {
					return ["wp", request.replace("@wordpress/", "")];
				}
				return undefined;
			},
		}),

		// 2. Clean output folder before each build
		new CleanWebpackPlugin(),

		// 3. Extract CSS into separate files
		new MiniCssExtractPlugin({
			filename: "../css/[name].min.css",
		}),

		// 4. Purge unused CSS
		new PurgeCSSPlugin({
			paths: [
				...glob.sync(`${PATHS.src}/**/*.{js,jsx,php}`, { nodir: true }),
				...glob.sync(`${PATHS.app}/**/*.php`, { nodir: true }),
				...glob.sync(`${PATHS.core}/**/*.php`, { nodir: true }),
			],
			safelist: { standard: [/wpmudev-/] },
		}),

		// 5. Generate RTL CSS automatically
		new RTLPlugin({
			filename: "../css/[name]-rtl.min.css",
		}),
	],

	// -------------------------------------------------------------------------
	// Optimization and minification
	// -------------------------------------------------------------------------
	optimization: {
		minimize: true,
		minimizer: [
			new TerserPlugin({ extractComments: false }),
			new CssMinimizerPlugin(),
		],
		splitChunks: {
			chunks: "all",
			name: "vendors",
			automaticNameDelimiter: "-",
		},
	},

	// -------------------------------------------------------------------------
	// Performance hints
	// -------------------------------------------------------------------------
	performance: {
		hints: "warning",
		maxEntrypointSize: 500000,
		maxAssetSize: 500000,
	},

	// -------------------------------------------------------------------------
	// Stats configuration
	// -------------------------------------------------------------------------
	stats: { children: true },
};
