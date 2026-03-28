import baseConfig from '@cakephp/docs-skeleton/config'

import { createRequire } from "module";
const require = createRequire(import.meta.url);
const toc_en = require("./toc_en.json");

const versions = {
  text: "5.x",
  items: [
    { text: "5.x (current)", link: "book.cakephp.org/migrations/5.x/", target: '_self' },
    { text: "4.x", link: "https://book.cakephp.org/migrations/4/", target: '_self' },
    { text: "3.x", link: "https://book.cakephp.org/migrations/3/", target: '_self' },
    { text: "2.x", link: "https://book.cakephp.org/migrations/2/", target: '_self' },
  ],
};

// This file contains overrides for .vitepress/config.js
export default {
  extends: baseConfig,
  srcDir: 'en',
  title: 'Migrations plugin',
  description: 'Migrations - CakePHP migrations Documentation',
  base: "/5.x/",
  rewrites: {
    "en/:slug*": ":slug*",
  },
  sitemap: {
    hostname: "https://book.cakephp.org/migrations/5.x/",
  },
  themeConfig: {
    socialLinks: [
      { icon: "github", link: "https://github.com/cakephp/cakephp" },
    ],
    editLink: {
      pattern: "https://github.com/cakephp/migrations/edit/5.x/docs/:path",
      text: "Edit this page on GitHub",
    },
    sidebar: toc_en,
    nav: [
      { text: "CakePHP Book", link: "https://book.cakephp.org/" },
      { ...versions },
    ],
  },
  substitutions: {},
  locales: {
    root: {
      label: "English",
      lang: "en",
    },
  },
};
