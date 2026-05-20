import type { Preview } from "@storybook/react";
import { withThemeByClassName } from "@storybook/addon-themes";

// Импортировать глобальные CSS-переменные из design-tokens
import "../../design-tokens/src/index.css";

const preview: Preview = {
    parameters: {
        actions: { argTypesRegex: "^on[A-Z].*" },
        controls: {
            matchers: {
                color: /(background|color)$/i,
                date: /Date$/i,
            },
        },
        backgrounds: {
            default: "light",
            values: [
                { name: "light", value: "var(--color-background)" },
                { name: "dark", value: "var(--color-background-dark)" },
            ],
        },
        a11y: {
            config: {
                rules: [
                    { id: "color-contrast", enabled: true },
                ],
            },
        },
    },

    decorators: [
        withThemeByClassName({
            themes: {
                light: "",
                dark: "dark",
            },
            defaultTheme: "light",
        }),
    ],
};

export default preview;
