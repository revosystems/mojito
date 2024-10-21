# Mojito

## Basic usage

### Tailwind CSS

To set up the Mojito Package in your project, you need to add the specified Tailwind CSS colors to the safelist in your tailwind.config.js file.

Here's how you can add the safelist in your tailwind.config.js file:

```php
    // All the colors are used for the PurchaseOrderStatus enum.
    safelist: [
        'text-blue-800',
        'bg-blue-400/20',
        'text-yellow-800',
        'bg-yellow-400/20',
        'text-green-800',
        'bg-green-400/20',
        'text-zinc-800',
        'bg-zinc-400/20'
    ],
```

By adding these colors to the safelist, you are instructing Tailwind CSS to keep the classes that use these colors in the final CSS output. This ensures that the Mojito Package's colors are available for use in your project.

Additionally, make sure to compile the assets (`npm run prod`) for the changes to take effect.
