export async function resolve(specifier, context, nextResolve) {
    try {
        return await nextResolve(specifier, context);
    } catch (error) {
        const isExtensionlessRelativeImport =
            error?.code === "ERR_MODULE_NOT_FOUND" &&
            specifier.startsWith(".") &&
            !/\.[a-z0-9]+$/i.test(specifier);

        if (!isExtensionlessRelativeImport) {
            throw error;
        }

        return nextResolve(`${specifier}.js`, context);
    }
}
