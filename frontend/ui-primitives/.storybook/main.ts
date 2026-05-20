import type { StorybookConfig } from "@storybook/react-vite";
import path from "path";

const config: StorybookConfig = {
    stories: [
        "../src/**/*.mdx",
        "../src/**/*.stories.@(js|jsx|mjs|ts|tsx)",
    ],

    addons: [
        "@storybook/addon-essentials",
        "@storybook/addon-a11y",
        "@storybook/addon-interactions",
        "@storybook/addon-themes",
    ],

    framework: {
        name: "@storybook/react-vite",
        options: {},
    },

    core: {
        disableTelemetry: true,
    },

    viteFinal: async (config) => {
        config.resolve = config.resolve ?? {};
        config.resolve.alias = {
            ...config.resolve.alias,
            "@opora/design-tokens": path.resolve(__dirname, "../../design-tokens/src"),
        };
        return config;
    },

    docs: {
        autodocs: "tag",
    },

    typescript: {
        check: true,
    },
};

export default config;
