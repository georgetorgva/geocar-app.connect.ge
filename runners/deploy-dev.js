#!/usr/bin/env node

var FtpDeploy = require("ftp-deploy");

var ftpDeploy = new FtpDeploy();

var projectName = process.argv[2] || "";
var FTP_HOST = process.argv[3] || "";
var FTP_PORT = process.argv[4] || "";
var FTP_USER = process.argv[5] || "";
var FTP_PASS = process.argv[6] || "";

if (!projectName || !FTP_HOST || !FTP_PORT || !FTP_USER || !FTP_PASS)
    throw "Not enough config to deploy";

var config = {
    user: FTP_USER,
    password: FTP_PASS,
    host: FTP_HOST,
    port: FTP_PORT,
    localRoot: "./",
    include: ["*", "**/*", ".*"],
    remoteRoot: projectName + ".connect.ge/",
    exclude: [
        ".git/**",
        ".editorconfig",
        ".gitignore",
        ".gitmodules",
        ".gitattributes",
        ".gitlab-ci.yml",
        ".styleci.yml",
        ".env.example",
        "artisan",
        "composer.json",
        "composer.lock",
        "package.json",
        "package-lock.json",
        "phpunit.xml",
        "README.md",
        "webpack.mix.js",
        "runners/**",
        "database/**",
        "vendor/**",
        "node_modules/**",
        "public/static/**",
        "vue-application/**",
        "vue-application/**/.*",
    ],
    forcePasv: true,
    continueOnError: true,
    //debug: console.log
};

ftpDeploy.on("uploading", function (data) {
    data.totalFilesCount; // total file count being transferred
    data.transferredFileCount; // number of files transferred
    data.filename; // partial path with filename being uploaded
});
// ftpDeploy.on('uploaded', function(data) {
// 	console.log(data);         // same data as uploading event
// });
// ftpDeploy.on('log', function(data) {
// 	console.log(data);         // same data as uploading event
// });

ftpDeploy.deploy(config, function (err) {
    if (err) throw err;
    else console.log("finished");
});
