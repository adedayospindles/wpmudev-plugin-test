module.exports = function (grunt) {
	require("load-grunt-tasks")(grunt);

	const copyFiles = [
		"app/**",
		"core/**",
		"languages/**",
		"assets/**", // Include compiled assets
		"uninstall.php",
		"changelog.txt",
		"wpmudev-plugin-test.php",

		// Runtime dependencies
		"vendor/**",

		// Exclusions inside vendor
		"!vendor/**/*Test*",
		"!vendor/**/tests/**",
		"!vendor/**/test/**",
		"!vendor/**/docs/**",
		"!vendor/**/doc/**",
		"!vendor/**/examples/**",
		"!vendor/**/example/**",
		"!vendor/**/README*",
		"!vendor/**/CHANGELOG*",
		"!vendor/**/CHANGES*",
		"!vendor/**/UPGRADE*",

		// General exclusions
		"!**/*.map", // Exclude source maps
		"!QUESTIONS.md",
		"README.md",
		"composer.json",
		"package.json",
		"!Gruntfile.js",
		"!gulpfile.js",
		"!webpack.config.js",
		"!phpcs.ruleset.xml",
		"!phpunit.xml.dist",
		"!src/**", // Exclude raw JSX/TS sources
		"!tests/**", // Exclude unit tests
	];

	const excludeCopyFilesPro = copyFiles.slice(0).concat(["changelog.txt"]);

	// Load changelog safely
	let changelog = "";
	if (grunt.file.exists(".changelog")) {
		changelog = grunt.file.read(".changelog");
	}

	grunt.initConfig({
		pkg: grunt.file.readJSON("package.json"),

		// Clean temp folders and release copies
		clean: {
			temp: ["**/*.tmp", "**/.afpDeleted*", "**/.DS_Store"],
			assets: ["assets/css/**", "assets/js/**"],
			folder_v2: ["build/**"],
		},

		checktextdomain: {
			options: {
				text_domain: "wpmudev-plugin-test",
				keywords: [
					"__:1,2d",
					"_e:1,2d",
					"_x:1,2c,3d",
					"esc_html__:1,2d",
					"esc_html_e:1,2d",
					"esc_html_x:1,2c,3d",
					"esc_attr__:1,2d",
					"esc_attr_e:1,2d",
					"esc_attr_x:1,2c,3d",
					"_ex:1,2c,3d",
					"_n:1,2,4d",
					"_nx:1,2,4c,5d",
					"_n_noop:1,2,3d",
					"_nx_noop:1,2,3c,4d",
				],
			},
			files: {
				src: [
					"app/templates/**/*.php",
					"core/**/*.php",
					"!core/external/**", // Exclude external libs
					"google-analytics-async.php",
				],
				expand: true,
			},
		},

		copy: {
			pro: {
				files: [
					{
						expand: true,
						src: excludeCopyFilesPro,
						dest: "build/<%= pkg.name %>/",
					},
					{
						expand: true,
						cwd: "assets/",
						src: ["**/*"], // Include all compiled assets
						dest: "build/<%= pkg.name %>/assets/",
					},
				],
			},
		},

		compress: {
			pro: {
				options: {
					mode: "zip",
					archive: "./build/<%= pkg.name %>-<%= pkg.version %>.zip",
				},
				expand: true,
				cwd: "build/<%= pkg.name %>/",
				src: ["**/*"],
				dest: "<%= pkg.name %>/",
			},
		},
	});

	grunt.loadNpmTasks("grunt-search");

	grunt.registerTask("version-compare", ["search"]);

	grunt.registerTask("finish", function () {
		const json = grunt.file.readJSON("package.json");
		const file = "./build/" + json.name + "-" + json.version + ".zip";
		grunt.log.writeln("Process finished.");
		grunt.log.writeln("Built plugin file: " + file);
	});

	// Build task
	grunt.registerTask("build", ["checktextdomain", "copy:pro", "compress:pro"]);

	// Pre-build cleanup
	grunt.registerTask("preBuildClean", [
		"clean:temp",
		"clean:assets",
		"clean:folder_v2",
	]);
};
