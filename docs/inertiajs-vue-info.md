### Documentation > Submitting Feedback

Source: https://inertiajs.com/docs/v3/the-basics/pages

If you encounter incorrect, outdated, or confusing documentation, you should submit specific and actionable feedback. Feedback should be submitted only when there is a clear issue to report rather than for every page visited.

--------------------------------

### AgentInstructions > Submitting Feedback

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

When providing feedback on documentation, ensure that reports are specific and actionable. Feedback should be submitted via a POST request to the designated endpoint only when identifying incorrect, outdated, or confusing information.

--------------------------------

### AgentInstructions > Submitting Feedback

Source: https://inertiajs.com/docs/v3/security/authorization

When submitting feedback regarding documentation, ensure that your report is specific and actionable. Feedback should be submitted via a POST request to the designated endpoint with the current page path and a clear description of the issue.

--------------------------------

### AgentInstructions > Submitting Feedback

Source: https://inertiajs.com/docs/v3/installation/community-adapters

If you encounter documentation that is incorrect, outdated, or confusing, you are encouraged to submit feedback. Please ensure that feedback is specific and actionable, and only submit reports when there is a clear issue to address.

--------------------------------

### AgentInstructions > Submitting Feedback

Source: https://inertiajs.com/docs/v3/core-concepts/how-it-works

Users are encouraged to submit specific and actionable feedback regarding incorrect, outdated, or confusing documentation by sending a POST request to the designated feedback endpoint with the relevant page path and description of the issue.

--------------------------------

### AgentInstructions > Submitting Feedback

Source: https://inertiajs.com/docs/v3/the-basics/view-transitions

If you encounter incorrect, outdated, or confusing documentation, you should submit feedback via a POST request to the Inertia documentation feedback endpoint. Please only submit feedback when you have specific and actionable information to report.

--------------------------------

### AgentInstructions > Submitting Feedback

Source: https://inertiajs.com/docs/v3/the-basics/layouts

When you encounter incorrect, outdated, or confusing documentation, you should submit specific and actionable feedback. Feedback should be sent via a POST request to the designated feedback endpoint with the current page path and a description of the issue.

--------------------------------

### AgentInstructions > Submitting Feedback

Source: https://inertiajs.com/docs/v3/getting-started

If you encounter incorrect, outdated, or confusing documentation, you are encouraged to submit actionable feedback. Feedback should be submitted via a POST request to the designated endpoint with the current page path and a description of the issue.

--------------------------------

### Title & Meta

Source: https://inertiajs.com/docs/v3/the-basics/title-and-meta

Inertia applications render within the document body, meaning they cannot directly modify the document head. To manage head elements such as titles and meta tags, Inertia provides a Head component that replaces elements not already present in the server-side root template.

--------------------------------

### AgentInstructions > Submitting Feedback

Source: https://inertiajs.com/docs/v3/advanced/code-splitting

If you encounter incorrect, outdated, or confusing documentation, you may submit feedback via a POST request to the provided feedback endpoint. Please only submit feedback for specific and actionable issues.

--------------------------------

### AgentInstructions > Submitting Feedback

Source: https://inertiajs.com/docs/v3/security/history-encryption

If you encounter incorrect, outdated, or confusing documentation, you may submit feedback via a POST request to the provided feedback endpoint. Please only submit feedback for specific and actionable issues rather than for every page visited.

--------------------------------

### Server-Side Setup

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

Inertia is optimized for Laravel, which serves as the primary framework for documentation examples. Users working with other server-side frameworks should consult the specific documentation provided by the respective community adapter.

--------------------------------

### Demo Application > Submitting Feedback

Source: https://inertiajs.com/docs/v3/getting-started/demo-application

If you encounter incorrect, outdated, or confusing documentation, you can submit feedback via POST to a specific endpoint. The request body should be a JSON object containing the path of the current page and a description of the issue. Only submit feedback for specific and actionable issues.

--------------------------------

### Changes > Head Element Attributes

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

The 'inertia' attribute used within the document head has been renamed to 'data-inertia'. Developers must update their root Blade templates to reflect this change for all head elements.

--------------------------------

### Submitting Feedback

Source: https://inertiajs.com/docs/v3/core-concepts/who-is-it-for

If you find incorrect, outdated, or confusing documentation, you can submit feedback via a POST request to https://inertiajs.com/docs/_mintlify/feedback/inertiajs/agent-feedback. The request body should be a JSON object containing the current page path and a description of the issue. Feedback should be specific and actionable.

--------------------------------

### Global Flash Event

Source: https://inertiajs.com/docs/v3/data-props/flash-data

Inertia.js supports native browser events for flash data, allowing developers to listen for flash notifications directly on the document object.

--------------------------------

### Scroll Containers

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The infinite scroll component is designed to function within any scrollable container, rather than being restricted to the main document. It automatically detects and adapts to custom scroll containers, ensuring that trigger detection and scroll calculations are performed relative to the specific container instead of the document window.

--------------------------------

### Scroll Management > Scroll Resetting

Source: https://inertiajs.com/docs/v3/advanced/scroll-management

Inertia automatically mimics standard browser behavior by resetting the scroll position to the top of the document when navigating between pages. Additionally, it tracks and restores the scroll position for each page during forward and backward history navigation.

--------------------------------

### Title & Meta

Source: https://inertiajs.com/docs/v3/the-basics/title-and-meta

The Head component is not available in the Svelte adapter because Svelte provides its own native svelte:head component for managing document head elements.

--------------------------------

### Upgrade Guide for v3.0

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Key features in v3.0 include standalone HTTP requests using the `useHttp` hook, which do not trigger page visits. It also introduces optimistic updates with automatic rollback for instant data changes before server confirmation, and layout props for sharing data between pages and layouts using the `useLayoutProps` hook.

--------------------------------

### Client-Side Setup > Laravel Starter Kits

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

Laravel's starter kits offer pre-built scaffolding for new Inertia applications, providing the quickest way to begin an Inertia project with Laravel and Vue or React. For manual installation, refer to the detailed documentation.

--------------------------------

### Data

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

For convenience, the `get()`, `post()`, `put()`, and `patch()` methods all accept `data` as their second argument.

--------------------------------

### Removing Listeners

Source: https://inertiajs.com/docs/v3/advanced/events

For scenarios involving native browser events, you can manage listeners using the standard browser removeEventListener method. This provides an alternative approach for handling events that are dispatched globally within the document.

--------------------------------

### Data

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

You may use the `data` option to add data to the request.

--------------------------------

### Upgrade Guide for v3.0

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Additional improvements in v3.0 include instant visits that update the target component before the server responds, form component generics for type-safe errors, and the ability to disable SSR per-route via middleware or facade. It also offers improved SSR error messages with component names and URLs, enum support in `Inertia::render()` responses, and page object access in the resolve callback for context-aware component resolution.

--------------------------------

### The Protocol > HTML Responses

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

The initial request to an Inertia application is a standard browser request. The server responds with a full HTML document that includes site assets (CSS, JavaScript) and a root `<div>` for mounting the client-side app. A `<script type="application/json">` element within this HTML contains the JSON-encoded page object, which Inertia uses to boot the client-side framework and render the initial page component.

--------------------------------

### Introduction > Support Policy

Source: https://inertiajs.com/docs/v3/getting-started

Inertia.js adheres to the same support policy as Laravel. When a new major version is released, the previous version is supported with bug fixes for 6 months and security fixes for 12 months following the release date.

--------------------------------

### Routing > Shorthand Routes

Source: https://inertiajs.com/docs/v3/the-basics/routing

For pages that do not require complex controller logic, such as static informational pages, routes can be defined to point directly to a component using a shorthand method.

--------------------------------

### Form Component

Source: https://inertiajs.com/docs/v3/the-basics/forms

The `<Form>` component natively supports complex data structures, including nested data, file uploads, and the use of dotted key notation for field naming.

--------------------------------

### How It Works > Intercepting Requests

Source: https://inertiajs.com/docs/v3/core-concepts/how-it-works

When an Inertia-driven XHR request is made, the server identifies it and returns a JSON response containing the necessary page component name and data instead of a full HTML document. Inertia then dynamically updates the browser's history state and swaps the current page component with the new one.

--------------------------------

### Scroll Management > Scroll Regions

Source: https://inertiajs.com/docs/v3/advanced/scroll-management

For applications that use custom scrollable elements with the CSS overflow property rather than the document body, Inertia's default scroll management will not function. To enable management for these elements, you must add the scroll-region attribute to the specific container element.

--------------------------------

### The Page Object

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

The page object includes core properties such as the component name, page props, the current URL, and the asset version. It may also contain optional configuration for history encryption, fragment preservation, and various prop merging strategies.

--------------------------------

### Upgrade Guide for v3.0

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Server-Side Rendering (SSR) in v3.0 is simplified, working automatically in Vite development mode without requiring a separate Node.js server. Exception handling is improved, allowing custom Inertia error pages to be rendered directly from your exception handler, complete with shared data.

--------------------------------

### Form Helper > Submission Methods

Source: https://inertiajs.com/docs/v3/the-basics/forms

Form submission methods include get, post, put, patch, and delete. These methods support standard visit options such as preserveState and preserveScroll, as well as event callbacks that allow for custom logic upon successful submission.

--------------------------------

### The Protocol > Inertia Responses

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

After the Inertia app is booted, all subsequent requests are made via XHR with an `X-Inertia` header set to `true`. This signals to the server that the request is from Inertia, not a standard full-page visit. In response, the server returns a JSON payload containing an encoded page object instead of a full HTML document.

--------------------------------

### Introduction > Not a Framework

Source: https://inertiajs.com/docs/v3/getting-started

Inertia is not a framework or a replacement for existing server-side or client-side frameworks. Instead, it acts as a bridge or glue between them, utilizing adapters to facilitate communication. Official client-side adapters are available for React, Vue, and Svelte, while server-side adapters are provided for Laravel, Rails, Phoenix, and Django.

--------------------------------

### Wayfinder

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

If you provide both a Wayfinder object and specify the `method` option, the `method` option will take precedence.

--------------------------------

### Upgrade Guide for v3.0

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Inertia.js v3.0 focuses on simplicity and developer experience, replacing Axios with a built-in XHR client for a smaller bundle. Server-Side Rendering (SSR) now works out of the box during development without a separate Node.js server. The new `@inertiajs/vite` plugin automates page resolution and SSR configuration.

--------------------------------

### Upgrade Guide for v3.0

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

To upgrade to Inertia.js v3.0, install the client-side adapter for your framework (e.g., `@inertiajs/vue3`, `@inertiajs/react`, `@inertiajs/svelte`) and the optional `@inertiajs/vite` plugin. Then, upgrade the `inertiajs/inertia-laravel` package. After these steps, republish the Inertia configuration file and clear cached views as the configuration and Blade directive output have changed.

--------------------------------

### Introduction

Source: https://inertiajs.com/docs/v3/getting-started

Inertia is a modern approach to building server-driven web applications that enables the creation of fully client-side rendered, single-page apps without the typical complexity of modern SPA development. It leverages existing server-side patterns, eliminating the need for client-side routing or a dedicated API. Developers can continue to build controllers and page views as they normally would within their preferred backend framework.

--------------------------------

### Page Components

Source: https://inertiajs.com/docs/v3/advanced/typescript

You can enhance type safety for resolving page components by utilizing the `import.meta.glob` result. This allows for better type checking when the application needs to dynamically load and resolve different page components.

--------------------------------

### Creating Layouts

Source: https://inertiajs.com/docs/v3/the-basics/layouts

A layout is a standard component that accepts child content and is not specific to Inertia. You can use a layout by wrapping your page content with it directly. However, this method causes the layout instance to be destroyed and recreated on each visit.

--------------------------------

### Title & Meta > Title Shorthand

Source: https://inertiajs.com/docs/v3/the-basics/title-and-meta

For simple title updates, you can use the title shorthand by passing the title string directly as a prop to the Head component instead of nesting a title tag.

--------------------------------

### Pages Shorthand

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

The pages shorthand allows developers to specify a custom directory for searching page components. For more granular control, an object can be provided to define the search path, file extensions, lazy-loading behavior, and name transformation logic.

--------------------------------

### Title & Meta > Title Callback

Source: https://inertiajs.com/docs/v3/the-basics/title-and-meta

You can globally modify page titles by defining a title callback within the createInertiaApp setup method. This is useful for automatically appending or prepending an application name to every page title, and it works whether the title is set via the shorthand prop or a nested title tag.

--------------------------------

### Method

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

When making manual visits, you may use the `method` option to set the request's HTTP method to `get`, `post`, `put`, `patch` or `delete`. The default method is `get`.

--------------------------------

### Infinite Scroll > Previous and Next Slots

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The previous and next slots are rendered above and below the main content area. These slots are primarily intended for implementing manual load controls, providing developers with the necessary state and functions to manage data fetching.

--------------------------------

### Inertia::scroll() Method

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

To streamline the setup of custom metadata resolution for `Inertia::scroll()`, you can define a macro in your `AppServiceProvider`. This allows you to reuse your custom scrolling logic across multiple controllers, making your code more maintainable.

--------------------------------

### Layouts

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Layout components allow developers to define shared UI elements, such as navigation bars, sidebars, or footers, in a single location. By using layouts, you can wrap your pages with these common elements automatically, ensuring consistency across your application.

--------------------------------

### Layout Props

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Layout props facilitate the passing of dynamic data from a page to its persistent layout. This mechanism allows layouts to handle features like page titles, active navigation states, or sidebar toggles by defining defaults that can be overridden by individual pages.

--------------------------------

### Page Props

Source: https://inertiajs.com/docs/v3/advanced/typescript

To provide type information for page-specific props, pass a generic type to the `usePage()` hook. These props are combined with your global `sharedPageProps`, ensuring that you have both autocomplete and type checking for all your page data, whether it's shared or specific.

--------------------------------

### Scoped Flash Data

Source: https://inertiajs.com/docs/v3/advanced/typescript

The router.flash method accepts a generic type to define page or section-specific flash data. This allows developers to manage flash messages with specific types, independent of global flash data configurations.

--------------------------------

### Inertia::scroll() Method

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The `Inertia::scroll()` method enables server-side configuration for infinite scrolling. It automatically handles merging new data by appending or prepending it to existing content, rather than replacing it. It also normalizes pagination metadata for frontend components.

--------------------------------

### Forms

Source: https://inertiajs.com/docs/v3/the-basics/forms

For forms that upload files, the `progress` property provides information about the current upload progress, including the percentage. This allows for the display of upload progress indicators.

--------------------------------

### Forms > Dotted Key Notation

Source: https://inertiajs.com/docs/v3/the-basics/forms

The `<Form>` component supports dotted key notation for creating nested objects from flat input names. This provides a convenient way to structure form data.

--------------------------------

### Shared Data > Sharing Data

Source: https://inertiajs.com/docs/v3/data-props/shared-data

Shared data should be namespaced appropriately to avoid collisions with page props. This ensures that data from different sources does not overwrite each other.

--------------------------------

### Installation > Register middleware

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

The `HandleInertiaRequests` middleware includes a `version()` method for setting your asset version and a `share()` method for defining shared data across your application.

--------------------------------

### Responses > Creating Responses

Source: https://inertiajs.com/docs/v3/the-basics/responses

When passing data to page components, it's recommended to only return the minimum data required to ensure pages load quickly. Be mindful that all data returned from controllers is visible client-side, so sensitive information should be omitted.

--------------------------------

### Inertia::scroll() Method

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

You can customize the behavior of `Inertia::scroll()` by providing additional arguments. This includes specifying a custom data wrapper key (defaults to 'data') and providing custom metadata resolution using a callback or an instance of `ProvidesScrollMetadata`. This is particularly useful when integrating with third-party pagination libraries.

--------------------------------

### Layouts

Source: https://inertiajs.com/docs/v3/the-basics/pages

Inertia supports persistent layouts that remain active across page navigations. These layouts allow for the sharing of common UI elements and support passing dynamic data between pages and their respective layouts.

--------------------------------

### Nested Prop Types

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Classes that implement the ProvidesInertiaProperties interface are now compatible with any nesting level within the response data structure.

--------------------------------

### Manual Visits

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

While manual visits are powerful, Inertia provides several shortcut methods for common HTTP verbs such as get, post, put, patch, and delete. These methods offer a more convenient syntax while supporting the same configuration options as the primary visit method.

--------------------------------

### Data

Source: https://inertiajs.com/docs/v3/the-basics/links

When making POST or PUT requests, you can add additional data using the `data` prop. This data can be provided as either an object or a FormData instance.

--------------------------------

### Form Context

Source: https://inertiajs.com/docs/v3/the-basics/forms

The context provided by `useFormContext` exposes all the properties and methods that are available through the `<Form>` component's slot props. This ensures consistency and allows children to utilize the full range of form functionalities.

--------------------------------

### Client Side Visits

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

Ensure that any route you navigate to on the client side is also defined on the server side. If the user refreshes the page, the server needs to know how to render the requested page.

--------------------------------

### Nested Layouts

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Nested layouts allow for the creation of complex page arrangements by wrapping components in multiple layers. By passing an array of layout components, you can define a hierarchy of wrappers for your pages.

--------------------------------

### Slots > Default Slot

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The default slot serves as the main content area for rendering data items. It provides access to loading state information, such as general loading status and specific states for loading previous or next content.

--------------------------------

### Reverse Mode

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

In reverse mode, the component flips the loading directions so that scrolling up loads the next page (older content) and scrolling down loads the previous page (newer content). The component handles loading positioning, but you are responsible for reversing your content to display in the correct order.

--------------------------------

### Installation > Setup root template

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

Create a `resources/views/app.blade.php` file. This root template is loaded on the initial page visit. Inertia provides Blade components, `<x-inertia::head />` and `<x-inertia::app />`, for rendering head and body content respectively. The `<x-inertia::app />` component renders a `<div>` with an `id` of `app`, which serves as the mounting point for your JavaScript application. This `id` can be customized.

--------------------------------

### Installation > Setup root template

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

The `<x-inertia::head />` component supports fallback content via its slot for managing head elements when Server-Side Rendering (SSR) is not active.

--------------------------------

### Submitting Data

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

The hook provides convenience methods for standard HTTP verbs including get, post, put, patch, and delete. A generic submit method is also available to handle dynamic HTTP methods as needed. Each of these methods returns a Promise that resolves with the parsed JSON response data.

--------------------------------

### Form Helper

Source: https://inertiajs.com/docs/v3/the-basics/forms

The useForm helper provides programmatic control over form data and submission behavior. It is designed for scenarios where more flexibility is required than what the standard form component offers.

--------------------------------

### Forms > Form Data and History State

Source: https://inertiajs.com/docs/v3/the-basics/forms

To enable Inertia to store a form's data and errors within the browser's history state, provide a unique form key when instantiating the form. This allows for state restoration across navigation.

--------------------------------

### The Protocol > Request Lifecycle Diagram

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

The request lifecycle begins with a standard initial visit, where the server returns an HTML skeleton containing hydrated data. Subsequent interactions trigger Inertia requests (XHR with `X-Inertia: true`), which receive JSON payloads. Inertia then dynamically hydrates and swaps page components without requiring a full page reload.

--------------------------------

### Head Component > Multiple Head Instances

Source: https://inertiajs.com/docs/v3/the-basics/title-and-meta

You can use multiple instances of the `<Head>` component throughout your application. Layouts can define default `<Head>` elements, and individual pages can override them. Inertia renders only one `<title>` tag, but stacks other tags. Use the `head-key` property to prevent duplicate tags.

--------------------------------

### Validating Multiple Fields

Source: https://inertiajs.com/docs/v3/the-basics/forms

The validation system allows for the validation of multiple specific fields simultaneously using the only option. This approach is particularly effective for multi-step or wizard-style forms where developers need to ensure that all visible fields are valid before allowing the user to progress to the next stage.

--------------------------------

### Manual Setup

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

If you prefer not to use the Vite plugin, you can provide the `resolve` and `setup` callbacks manually. The `resolve` callback is responsible for loading page components and receives the component name and the full page object. The `setup` callback is used to initialize the client-side framework.

--------------------------------

### The Page Object

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

Inertia uses a page object to share data between the server and client. This object contains the information necessary to render the page component, update browser history, and track asset versions.

--------------------------------

### SSR Entry Point

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

When configuring the SSR entry point, ensure that all necessary plugins, custom mixins, or global configurations used in your standard client-side application are also included in the SSR setup file. This ensures consistency between the server-rendered output and the client-side application.

--------------------------------

### Event Callbacks

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

In addition to global events, Inertia offers several per-visit event callbacks. These include `onBefore`, `onStart`, `onProgress`, `onSuccess`, `onError`, `onHttpException`, `onNetworkError`, `onCancel`, `onFinish`, `onPrefetching`, and `onPrefetched`. Each callback allows you to intercept and react to different stages of a visit.

--------------------------------

### Root Template Data

Source: https://inertiajs.com/docs/v3/the-basics/responses

Data passed to components can be accessed within the application's root Blade template using the $page variable. This is useful for dynamically setting meta tags such as Twitter cards or Open Graph tags based on the page props.

--------------------------------

### Targeting Named Layouts

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Nested and named layouts support static props through a tuple syntax. This allows you to associate specific configuration or data directly with the layout definition.

--------------------------------

### Default Layouts

Source: https://inertiajs.com/docs/v3/the-basics/layouts

The layout option in createInertiaApp enables the definition of a default layout for all pages, which reduces redundancy. Per-page layouts take precedence over these defaults, allowing for specific overrides when necessary.

--------------------------------

### Forms

Source: https://inertiajs.com/docs/v3/the-basics/forms

The `processing` property can be used to track the current submission status of a form. This is useful for disabling the submit button to prevent accidental double submissions.

--------------------------------

### Page Object with Scroll Props

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

When using Infinite scroll, the page object includes a `scrollProps` configuration. This configuration helps manage scroll-related data for pagination.

--------------------------------

### Installation > Register middleware

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

Set up the Inertia middleware by publishing the `HandleInertiaRequests` middleware using an Artisan command. Then, append this middleware to the `web` middleware group in your `bootstrap/app.php` file.

--------------------------------

### Links > Creating Links

Source: https://inertiajs.com/docs/v3/the-basics/links

When using HTTP methods other than GET (like POST, PUT, PATCH, DELETE) with the `<Link>` component, it's recommended to use the `as='button'` prop. This ensures the component renders a `<button>` element, avoiding potential accessibility issues with anchor tags for non-GET requests.

--------------------------------

### Form Helper

Source: https://inertiajs.com/docs/v3/data-props/remembering-state

The Inertia form helper allows for automatic persistence of form data and errors by providing a unique key during instantiation. This ensures that form state is remembered across page navigations.

--------------------------------

### Installation > Create your first response

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

Once the installation and middleware setup are complete, you are ready to create Inertia pages and render them using responses. This involves defining your controller logic to return Inertia responses.

--------------------------------

### Event Callbacks

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

Submit methods support various lifecycle callbacks to handle different stages of a request, such as progress tracking, success, or failure. Returning false from the onBefore callback provides a mechanism to prevent the request from executing.

--------------------------------

### Config File Restructuring

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

The Laravel configuration file for Inertia has been restructured to improve organization. Page-related settings are now grouped under a dedicated pages key, and the testing configuration has been simplified to streamline the setup process.

--------------------------------

### Scroll Management > Text Fragments

Source: https://inertiajs.com/docs/v3/advanced/scroll-management

Text fragments allow linking to specific content on a page using URL syntax. Because browsers strip these fragments before JavaScript executes, they only function if the target text is present in the initial HTML response. Consequently, using text fragments with Inertia requires enabling server-side rendering.

--------------------------------

### Events > Start

Source: https://inertiajs.com/docs/v3/advanced/events

The `start` event fires when a request to the server has begun. This event is useful for implementing features like loading indicators. Note that the `start` event cannot be cancelled.

--------------------------------

### File Uploads > File Upload Example

Source: https://inertiajs.com/docs/v3/the-basics/file-uploads

The Inertia form helper is recommended for file uploads because it provides built-in access to upload progress tracking. While the form helper simplifies the process, developers retain the flexibility to submit forms using manual Inertia visits if preferred.

--------------------------------

### Inertia.js > Architecture > Page Structure

Source: https://inertiajs.com/docs/v3/the-basics/pages

Inertia applications are structured so that each page corresponds to a specific controller and route, paired with a dedicated JavaScript component. This architecture allows developers to fetch only the data required for the current page, removing the need for a traditional API layer.

--------------------------------

### View Transitions

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

Enable View Transitions for a visit by setting the `viewTransition` option to `true`. This utilizes the browser's View Transitions API to animate page transitions.

--------------------------------

### Form Helper

Source: https://inertiajs.com/docs/v3/the-basics/optimistic-updates

The useForm helper provides an optimistic method that allows for optimistic updates during form submission. This enables developers to update the UI immediately before the server request completes.

--------------------------------

### Forms

Source: https://inertiajs.com/docs/v3/the-basics/forms

Inertia provides two primary ways to build forms: the `<Form>` component and the `useForm` helper. Both integrate with your server-side framework's validation and handle form submissions without full page reloads.

--------------------------------

### Inline Option

Source: https://inertiajs.com/docs/v3/the-basics/optimistic-updates

The optimistic callback can be provided directly within the visit options, offering an alternative to chaining the method. This inline option is also compatible with `useHttp` submit methods.

--------------------------------

### Remembering State > Multiple Components

Source: https://inertiajs.com/docs/v3/data-props/remembering-state

If you have multiple instances of the same component on a page that use the remember functionality, ensure each instance has a unique key. This can be achieved by including a unique identifier, like a model ID, in the key to differentiate between instances.

--------------------------------

### Forms > Optimistic Updates

Source: https://inertiajs.com/docs/v3/the-basics/forms

The `<Form>` component and `useForm` helper enable optimistic updates, allowing the UI to reflect changes immediately before the server confirms them. This enhances the user experience by providing instant feedback.

--------------------------------

### Manual Mode

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The manualAfter prop allows the infinite scroll component to automatically switch from automatic loading to manual mode after a specified number of pages have been loaded.

--------------------------------

### Layouts > Callback Props > Returning Props Only

Source: https://inertiajs.com/docs/v3/the-basics/layouts

A static object can be used within layout definitions when the props do not depend on page data. This provides a straightforward way to set fixed props for layouts.

--------------------------------

### The Page Object

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

On standard full page visits, the page object is JSON encoded into a script element within the HTML. During Inertia-specific visits, identified by the X-Inertia header, the page object is returned directly as a JSON payload.

--------------------------------

### Endpoint Tests

Source: https://inertiajs.com/docs/v3/advanced/testing

You can use assertion methods to verify the content of data provided to an Inertia response. These tools allow you to assert against specific property values, verify the length of array data, and scope assertions to nested structures.

--------------------------------

### Manual Mode

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

Manual mode disables automatic content loading during scrolling. Instead, it allows developers to control when content is loaded by utilizing the next and previous slots to trigger fetch actions.

--------------------------------

### Inertia.js > Architecture > Data Retrieval

Source: https://inertiajs.com/docs/v3/the-basics/pages

Inertia retrieves all necessary page data before the browser renders the page. This approach ensures that the application is ready immediately upon navigation, effectively eliminating the need for loading states or spinners when users move between pages.

--------------------------------

### Endpoint Tests

Source: https://inertiajs.com/docs/v3/advanced/testing

Scoping allows you to reduce repetition when asserting against nested properties. By using the has method with a closure, you can focus your assertions on a specific part of the data structure, including deeper levels accessed via dot notation.

--------------------------------

### Defining a Root Element

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

Inertia assumes that the application's root template contains an element with an id of app. If your application uses a different identifier for the root element, you can specify it using the id property during initialization.

--------------------------------

### Events

Source: https://inertiajs.com/docs/v3/advanced/events

Inertia provides an event system that allows developers to hook into the various lifecycle events of the library. This system enables tracking and responding to specific actions during the application's visit lifecycle.

--------------------------------

### The Page Object > Page Object with Merge Props

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

Merge props provide advanced control over how data is updated during navigation. The page object can include configurations for merging, prepending, or deep merging specific prop keys, as well as defining matching criteria for these operations.

--------------------------------

### Changes > Future Options Removed

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

The 'future' configuration namespace has been removed, as all previously optional future features are now enabled by default. Additionally, initial page data is now exclusively delivered via a JSON script element, ending support for the legacy 'data-page' attribute approach.

--------------------------------

### Navigate

Source: https://inertiajs.com/docs/v3/advanced/events

The navigate event is triggered upon successful page visits and when a user navigates through their browser history. This event cannot be canceled.

--------------------------------

### Visit Options

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

Inertia.js offers two visit options, `showProgress` and `async`, to manage loading indicators for individual requests. These options provide enhanced control over asynchronous operations and the display of progress feedback.

--------------------------------

### Slots > Loading Slot

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The loading slot acts as a fallback mechanism for displaying a loading indicator when content is being fetched. This is used when no specific before or after slots are defined, providing a default way to inform users that more content is being loaded.

--------------------------------

### Router Requests

Source: https://inertiajs.com/docs/v3/advanced/typescript

Router methods support generic type parameters for request data. This feature enables strict type checking for the data being sent during navigation or form submissions.

--------------------------------

### Remembering State > Multiple Components

Source: https://inertiajs.com/docs/v3/data-props/remembering-state

When multiple components on a page utilize Inertia's remember functionality, it's crucial to assign a unique key to each component. This key allows Inertia to correctly associate and restore the specific data to its corresponding component.

--------------------------------

### Layout Callbacks

Source: https://inertiajs.com/docs/v3/advanced/typescript

The `LayoutCallback` type ensures type safety for layout callbacks. These callbacks receive the page's props, typed via your global `sharedPageProps` configuration, and are expected to return a valid layout definition.

--------------------------------

### Form Helper > Data Transformation

Source: https://inertiajs.com/docs/v3/the-basics/forms

The transform method allows for modification of form data immediately before it is sent to the server. This is useful for formatting or adjusting data structures to meet specific server-side requirements.

--------------------------------

### Instant Visits > Page Props

Source: https://inertiajs.com/docs/v3/the-basics/instant-visits

A callback may also be passed to `pageProps`. The callback receives the current page's props and the shared props as arguments, so you may selectively spread them.

--------------------------------

### Code Splitting

Source: https://inertiajs.com/docs/v3/advanced/code-splitting

Developers can choose to disable lazy loading to eagerly bundle all pages into a single file. While this eliminates the need for per-page requests during navigation, it results in a larger initial bundle size.

--------------------------------

### Installation > Setup root template

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

For React applications, it's recommended to include the `@viteReactRefresh` directive before the `@vite` directive in your root template to enable Fast Refresh during development.

--------------------------------

### Prefetching

Source: https://inertiajs.com/docs/v3/data-props/prefetching

Inertia supports prefetching data for pages that are likely to be visited next. This improves perceived performance by allowing data to be fetched in the background while the user interacts with the current page.

--------------------------------

### Installation > Initialize the Inertia app

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

The Inertia Vite plugin simplifies the application entry point by handling page resolution and mounting automatically. It includes a default resolver that searches for page components within both the ./pages and ./Pages directories.

--------------------------------

### Flash Data > Accessing Flash Data

Source: https://inertiajs.com/docs/v3/data-props/flash-data

Flash data is accessible on the frontend via `page.flash`. You can also leverage global events or the `onFlash` callback to handle this data. This provides flexibility in how you manage and react to temporary data on the client-side.

--------------------------------

### Nested Prop Types

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Inertia v3 introduces support for nested prop types, allowing helpers like optional, defer, and merge to function within closures and nested arrays. The system resolves these at any depth using dot-notation paths for partial reload metadata. This functionality extends to the client side, where components and reload options support dot-notation for targeting specific nested properties.

--------------------------------

### Head Component > Server-Side Head Elements

Source: https://inertiajs.com/docs/v3/the-basics/title-and-meta

Include default head elements like `<title>` or `<meta>` tags in your root Blade template. The `<x-inertia::head>` Blade component handles this, preventing duplicates when Server-Side Rendering (SSR) is active and allowing client-side navigation to replace them.

--------------------------------

### Options > Error Handling

Source: https://inertiajs.com/docs/v3/the-basics/forms

Validation error handling can be configured to return either the first error message as a string or all error messages as an array. By default, the system simplifies errors to a single string, but this behavior can be modified to provide more comprehensive error feedback.

--------------------------------

### The data-inertia Attribute

Source: https://inertiajs.com/docs/v3/the-basics/title-and-meta

The Head component uses the data-inertia attribute to identify and manage specific elements within a Blade template. While the component automatically replaces the title tag, other elements like meta and link tags require this attribute to distinguish which ones should be tracked and updated during client-side navigation.

--------------------------------

### Default Layouts

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Default layouts can be applied conditionally based on the page name or page object metadata. This is useful for excluding specific sections, such as public pages, from a global layout.

--------------------------------

### Advanced Data Loading > Combining with Once Props

Source: https://inertiajs.com/docs/v3/data-props/partial-reloads

Chain the `once()` modifier onto an optional prop to ensure the data is resolved only once and remembered by the client across subsequent navigations. This is useful for data that doesn't change frequently and can be cached client-side.

--------------------------------

### Endpoint Tests

Source: https://inertiajs.com/docs/v3/advanced/testing

When working with arrays or collections, you can combine scoping with size assertions. This allows you to verify the total count of items while simultaneously scoping into the first item to perform further checks.

--------------------------------

### Manual Setup

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

The `laravel-vite-plugin` package offers a `resolvePageComponent` helper function. This utility can simplify the process of resolving page components, especially when working with Vite.

--------------------------------

### Custom Headers

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

The `headers` option allows you to add custom headers to a request.

--------------------------------

### Event Callbacks

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

Returning `false` from the `onBefore()` callback will cancel the visit. This provides a way to conditionally prevent visits from proceeding based on certain criteria.

--------------------------------

### Root Template Data

Source: https://inertiajs.com/docs/v3/the-basics/responses

The withViewData method allows developers to provide data specifically to the root Blade template without sending it to the JavaScript page component. Once defined, this data is accessible in the template as a standard Blade variable.

--------------------------------

### Custom

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

Import both NProgress and the Inertia router into your application. Then, set up event listeners for 'start' and 'finish' events. The 'start' event listener initiates NProgress, and the 'finish' event listener completes it.

--------------------------------

### Event Callbacks > Accessing the HTTP response

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

The onSuccess callback provides access to both the parsed response data and the full HTTP response object, which includes status codes and headers. This is useful for inspecting specific response details after a successful request.

--------------------------------

### Restoring State

Source: https://inertiajs.com/docs/v3/advanced/typescript

The router.restore method allows for the use of generics when retrieving state from history. This ensures that the restored data is correctly typed according to the application's requirements.

--------------------------------

### Props and Options

Source: https://inertiajs.com/docs/v3/the-basics/forms

The `<Form>` component accepts several props, many of which mirror the options available in Inertia's manual visit options. These props control various aspects of the form submission and how Inertia handles the subsequent page visit.

--------------------------------

### Forms > File Uploads

Source: https://inertiajs.com/docs/v3/the-basics/forms

When requests or form submissions involve files, Inertia automatically transforms the data into a `FormData` object. This functionality is supported across the `<Form>` component, `useForm` helper, and manual router submissions.

--------------------------------

### TypeScript > Global Configuration > Shared Page Props

Source: https://inertiajs.com/docs/v3/advanced/typescript

The `sharedPageProps` option defines the type of data that is shared with every page in your application. With this configuration, `page.props.auth` and `page.props.appName` will be properly typed everywhere.

--------------------------------

### Data Loading Attribute

Source: https://inertiajs.com/docs/v3/the-basics/links

Inertia automatically adds a data-loading attribute to link elements while an active request is in progress. This attribute is removed once the request completes, providing a reliable way to apply specific styles to links during the loading process.

--------------------------------

### TypeScript

Source: https://inertiajs.com/docs/v3/data-props/flash-data

Developers can configure the flash data type globally within their projects by utilizing TypeScript's declaration merging capabilities.

--------------------------------

### ProvidesInertiaProperty Interface

Source: https://inertiajs.com/docs/v3/the-basics/responses

The PropertyContext object enables advanced patterns such as merging new data with existing shared data. By accessing the property key, developers can retrieve shared data and combine it with new items before rendering.

--------------------------------

### Layouts > Callback Props

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Layout props can also be derived dynamically using a callback function. This function receives the current page's props and returns a layout definition with computed static props, enabling dynamic layout configurations based on page data.

--------------------------------

### Forms > Dotted Key Notation

Source: https://inertiajs.com/docs/v3/the-basics/forms

If you need literal dots in your field names (not as nested object separators), you can escape them using backslashes.

--------------------------------

### Custom

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

To use NProgress for custom loading indicators, install the library and add its styles to your project. You can include the NProgress styles using a CDN.

--------------------------------

### Slot Props > errors object

Source: https://inertiajs.com/docs/v3/the-basics/forms

The errors object supports dotted notation for nested fields. This feature enables the display of validation messages for complex form structures by referencing nested keys.

--------------------------------

### Visit Options > Async

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

The `async` option enables asynchronous requests without automatically showing the default progress indicator. It can be used independently or in conjunction with `showProgress` to customize the user experience during asynchronous operations.

--------------------------------

### useFormContext

Source: https://inertiajs.com/docs/v3/advanced/typescript

The `useFormContext()` function also accepts a generic type parameter. This allows for type-safe access to the form context from child components, ensuring that data and methods are used correctly.

--------------------------------

### Reverse Mode

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

For interfaces like chat applications or timelines where content is sorted descendingly, you can enable reverse mode. This configures the component to load older content when scrolling upward.

--------------------------------

### View Transitions

Source: https://inertiajs.com/docs/v3/the-basics/links

You can enable view transitions for a link to animate page changes. This feature utilizes the browser's native View Transitions API to provide smooth visual transitions between pages.

--------------------------------

### Slots

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The infinite scroll component provides various slots to customize the loading experience, including the ability to display custom loading indicators and manual load controls. These slots receive properties that provide access to loading state information and functions to trigger content loading.

--------------------------------

### Props and Options

Source: https://inertiajs.com/docs/v3/the-basics/forms

Some props are grouped under the `options` object to distinguish their purpose. For instance, `only`, `except`, and `reset` are related to partial reloads, not partial submissions. The general guideline is that top-level props manage the form submission itself, while props within `options` dictate how Inertia manages the resulting visit.

--------------------------------

### State Preservation

Source: https://inertiajs.com/docs/v3/the-basics/links

Preserve a page component's local state using the `preserve-state` prop to prevent a full re-render. This is particularly useful for forms, as it helps maintain input focus and avoids manual repopulation.

--------------------------------

### Infinite Scroll > Client-Side

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

On the client side, Inertia provides the `<InfiniteScroll>` component to automatically load additional pages of content. This component wraps the content that depends on the paginated data and accepts a `data` prop specifying the key of the prop containing your paginated data. It utilizes intersection observers to detect when users scroll near the end of the content and triggers requests to load the next page, merging new data with existing content.

--------------------------------

### Shared Data

Source: https://inertiajs.com/docs/v3/data-props/shared-data

Shared data allows you to make specific pieces of data available on numerous pages within your application without manually passing it in each response. This is useful for data like the current user that needs to be displayed in multiple locations, such as a site header. Shared data is automatically merged with the page props provided in your controller.

--------------------------------

### Wayfinder Integration > Wayfinder Configuration

Source: https://inertiajs.com/docs/v3/the-basics/instant-visits

To enable component information in Wayfinder route definitions, you must set the generate.inertia.component option to true in your configuration file. Once enabled and routes are regenerated, each route definition will include the specific component name that Inertia should render.

--------------------------------

### Event Callbacks

Source: https://inertiajs.com/docs/v3/advanced/events

Inertia offers event callbacks that are triggered when performing manual Inertia visits. These callbacks supplement the global events detailed elsewhere on this page.

--------------------------------

### TypeScript > Global Configuration > Layout Props

Source: https://inertiajs.com/docs/v3/advanced/typescript

The `layoutProps` option types the data accepted by `setLayoutProps()`. The `namedLayoutProps` option types the data accepted by `setLayoutProps('name', props)`, keyed by layout name.

--------------------------------

### Events > Before

Source: https://inertiajs.com/docs/v3/advanced/events

The `before` event is triggered just before a request is sent to the server. It's particularly useful for intercepting visits and can be used to prevent a visit from occurring. For example, you could prompt the user for confirmation before navigating away from the current page.

--------------------------------

### Routing > Customizing the Page URL

Source: https://inertiajs.com/docs/v3/the-basics/routing

Inertia resolves the current page URL by default using the request's full URL, stripped of the scheme and host. If custom URL resolution logic is required, developers can override this behavior by providing a resolver in the HandleInertiaRequests middleware or by using the global resolveUrlUsing method.

--------------------------------

### Progress Indicators > Programmatic Access

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

The hide and reveal methods utilize an internal counter to manage visibility when multiple parts of an application control the progress bar. The bar only becomes visible when the counter reaches zero, though the reveal method supports a force parameter to bypass this logic when necessary.

--------------------------------

### Client Side Visits

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

The `errorBag` option allows you to specify which error bag to use when handling validation errors in the `onError` callback.

--------------------------------

### Props and Options

Source: https://inertiajs.com/docs/v3/the-basics/forms

To style a form while it is processing, you can target the `form[inert]` selector. This allows for visual feedback to the user, such as reducing opacity or disabling pointer events on the form elements.

--------------------------------

### Instant Visits > Page Props

Source: https://inertiajs.com/docs/v3/the-basics/instant-visits

You may provide props for the intermediate page using the `pageProps` option. This is useful for passing data you already have on the current page, or for setting placeholder values to display loading states while the server responds. When `pageProps` is provided as an object, shared props are not automatically carried over; you are in full control of the intermediate page's props.

--------------------------------

### Resetting

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

When filters or other parameters change, it's often necessary to reset the infinite scroll data to ensure new results replace existing content rather than merging with it. This reset functionality can be achieved using the `reset` visit option, which ensures the data starts from the beginning.

--------------------------------

### Layouts > Static Props

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Static props can be passed directly within the persistent layout definition using a tuple. These props are set once when the layout is defined and remain constant across page navigations.

--------------------------------

### Forms > Resetting the Form

Source: https://inertiajs.com/docs/v3/the-basics/forms

To reset a form's values back to their default state, use the `reset()` method. This method can be called without arguments to reset all fields, or with specific field names to reset only those fields.

--------------------------------

### Page Object Changes

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

The page object properties clearHistory and encryptHistory are now optional. They are only included in the response payload when set to true, reducing unnecessary data transmission compared to previous versions where these properties were always present.

--------------------------------

### HTTP Requests > Basic Usage

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

The `useHttp` hook accepts initial data and returns reactive state along with methods for making HTTP requests. It offers a consistent API for handling HTTP operations.

--------------------------------

### Form Component

Source: https://inertiajs.com/docs/v3/the-basics/forms

The `<Form>` component in Inertia functions similarly to a standard HTML form but leverages Inertia's capabilities to prevent full page reloads. It offers the most straightforward approach to implementing forms within an Inertia application.

--------------------------------

### Inertia::scroll() Method

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The `Inertia::scroll()` method works seamlessly with all standard Laravel pagination methods, including `paginate()`, `simplePaginate()`, and `cursorPaginate()`. It also supports API resources when used with Laravel's resource collections.

--------------------------------

### View Transitions > Enabling Transitions

Source: https://inertiajs.com/docs/v3/the-basics/view-transitions

View transitions can be enabled for a visit by setting the viewTransition option to true. By default, this configuration applies a cross-fade transition between pages.

--------------------------------

### Loading Direction

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The default behavior of the infinite scroll component is to load content in both directions. This is particularly useful when users start on a middle page and need to scroll in both directions to access all content.

--------------------------------

### TypeScript > Global Configuration > Flash Data

Source: https://inertiajs.com/docs/v3/advanced/typescript

The `flashDataType` option defines the type of flash data in your application.

--------------------------------

### Shared Data > Accessing Shared Data

Source: https://inertiajs.com/docs/v3/data-props/shared-data

After sharing data server-side, it can be accessed within any of your pages or components using the `usePage` hook (in Vue and React) or the `page` store (in Svelte). This allows for easy access to shared information like user details within your frontend components.

--------------------------------

### Load When Visible

Source: https://inertiajs.com/docs/v3/data-props/load-when-visible

To load multiple props when an element becomes visible, provide an array to the `data` prop of the `WhenVisible` component.

--------------------------------

### Customizing the App

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

The withApp callback provides a hook to customize the application instance before it renders. This is useful for registering plugins, wrapping the application with providers, or setting context values.

--------------------------------

### Client-Side Flash

Source: https://inertiajs.com/docs/v3/data-props/flash-data

The client-side flash method supports passing a callback function, which provides access to the current flash data. This allows for dynamic updates or the complete replacement of existing flash values.

--------------------------------

### Flash Data > Flashing Data

Source: https://inertiajs.com/docs/v3/data-props/flash-data

You can flash data using the `Inertia::flash()` method, which accepts a key-value pair or an array of key-value pairs. This method can be chained with `back()` or `render()` for convenient data flashing during redirects or page renders.

--------------------------------

### Programmatic Access

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

You can trigger loading actions programmatically by using a template ref. This allows you to call methods like `fetchNext()`, `fetchPrevious()`, `hasNext()`, and `hasPrevious()` on the component instance.

--------------------------------

### Default Layouts > Using the Resolve Callback

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Default layouts can be configured within the resolve callback by mutating the resolved page component. This approach is particularly useful for applying layouts dynamically based on page data.

--------------------------------

### TypeScript > Global Configuration > Shared Page Props

Source: https://inertiajs.com/docs/v3/advanced/typescript

The `sharedPageProps` option in Inertia's global configuration allows you to define the type for data shared across all pages in your application. This ensures that properties like `page.props.auth` and `page.props.appName` are correctly typed throughout your project.

--------------------------------

### Head Component > Head Extension

Source: https://inertiajs.com/docs/v3/the-basics/title-and-meta

Create a custom head component that extends Inertia's `<Head>` component to set app-wide defaults. For example, you can automatically append your app's name to the page title.

--------------------------------

### Vite Plugin Setup > Production

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

For production environments, you need to build both the client and SSR bundles. After building, start the SSR server using the provided Artisan command.

--------------------------------

### TypeScript > Global Configuration > Flash Data

Source: https://inertiajs.com/docs/v3/advanced/typescript

The `flashDataType` option enables you to define the TypeScript type for flash data within your Inertia application. This helps in managing and type-checking data that is temporarily stored and displayed, such as notifications or messages.

--------------------------------

### HTTP Requests

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

For calls to an external API or fetching data from a non-Inertia endpoint, the `useHttp` hook provides the same developer experience as `useForm`, but for standalone HTTP requests. This hook is useful when a request does not need to trigger an Inertia page visit.

--------------------------------

### Global Configuration

Source: https://inertiajs.com/docs/v3/the-basics/view-transitions

You can enable view transitions globally for all visits by configuring the `visitOptions` callback when initializing your Inertia app. This ensures that view transitions are applied consistently across your application.

--------------------------------

### Client-Side Visits

Source: https://inertiajs.com/docs/v3/advanced/typescript

The router.push and router.replace methods utilize generic type parameters to enforce type safety for client-side visit props. This ensures that the data passed during manual visits matches the expected component properties.

--------------------------------

### Flash Data > Flashing Data

Source: https://inertiajs.com/docs/v3/data-props/flash-data

Flash data is scoped to the current request and is automatically persisted to the session by the middleware when redirecting. Once the flash data is sent to the client, it is cleared from the session and will not be available in subsequent requests, ensuring its one-time nature.

--------------------------------

### useHttp > File Uploads

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

When the data includes files, the hook automatically sends the request as `multipart/form-data`. Upload progress is available through the `progress` property.

--------------------------------

### Programmatic Prefetching

Source: https://inertiajs.com/docs/v3/data-props/prefetching

The `usePrefetch` hook allows you to track the prefetch state for the current page. It provides information such as `lastUpdatedAt`, `isPrefetching`, and `isPrefetched`, along with a `flush` method to clear the cache for the current page.

--------------------------------

### Slot Props

Source: https://inertiajs.com/docs/v3/the-basics/forms

The Form component provides a default slot that exposes reactive state and helper methods. This allows developers to access form processing status, validation errors, and utility functions directly within the component template.

--------------------------------

### Programmatic Prefetching

Source: https://inertiajs.com/docs/v3/data-props/prefetching

When prefetching programmatically, you can pass visit options to differentiate between various request configurations for the same URL, enabling more specific prefetching strategies.

--------------------------------

### Element Targeting

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The infinite scroll component automatically tracks content and assigns page numbers to elements to facilitate URL synchronization. When data items are not direct children of the component's root, you must specify the container element using the itemsElement prop. This allows the component to monitor the correct container and tag individual items as they load, ensuring the URL updates accurately based on the content visible in the viewport.

--------------------------------

### Links > Wayfinder

Source: https://inertiajs.com/docs/v3/the-basics/links

When integrating with Laravel's Wayfinder, the Inertia `<Link>` component can directly accept Wayfinder objects. The `Link` component intelligently infers the correct HTTP method and URL from the Wayfinder object, simplifying link creation.

--------------------------------

### Persistent Layouts

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Persistent layouts solve the issue of layout instances being destroyed and recreated on every visit. By specifying which layout to use for a page, Inertia manages the layout instance separately, ensuring it remains alive between visits. This is crucial for maintaining state within layouts, such as an audio player or a sidebar's scroll position.

--------------------------------

### Authentication > Laravel Starter Kits

Source: https://inertiajs.com/docs/v3/security/authentication

Laravel starter kits offer pre-built scaffolding for new Inertia applications, which includes integrated authentication functionality to help developers get started quickly.

--------------------------------

### Page Object with Once Props

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

When using once props, the page object includes an `onceProps` configuration. Each entry maps a key to the prop name and an optional expiration timestamp. The client sends loaded keys in the `X-Inertia-Except-Once-Props` header to avoid redundant data transfer.

--------------------------------

### Links

Source: https://inertiajs.com/docs/v3/the-basics/links

Inertia.js provides a single-page application experience by intercepting click events on links and preventing full page reloads. This is achieved using the Inertia `<Link>` component, which acts as a wrapper around standard anchor `<a>` tags.

--------------------------------

### Form Helper > Nested Data and Arrays

Source: https://inertiajs.com/docs/v3/advanced/typescript

Form types in Inertia.js v3 fully support nested objects and arrays. You can access and modify nested fields using dot notation, and the error keys are automatically typed to accurately reflect the structure of your form data.

--------------------------------

### Server-Side Setup > Laravel Starter Kits

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

Laravel starter kits offer pre-built scaffolding for new Inertia projects, providing the fastest route to begin development with Vue or React. Manual installation remains an alternative for those who prefer to configure their application environment independently.

--------------------------------

### The data-inertia Attribute

Source: https://inertiajs.com/docs/v3/the-basics/title-and-meta

By adding the data-inertia attribute to elements in a Blade template, the Head component adopts them for management. During the initial client-side navigation, the component matches these elements based on their keys to perform necessary replacements or removals.

--------------------------------

### Options

Source: https://inertiajs.com/docs/v3/the-basics/forms

Validation errors are simplified to strings by default, showing only the first error message for a field. This can be changed to keep errors as arrays, allowing you to display all validation error messages for fields with multiple rules.

--------------------------------

### Changes > Event Renames

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Global event names have been updated for clarity. The 'invalid' event is now 'httpException', and the 'exception' event is now 'networkError'. These changes apply to both global listeners and per-visit callbacks. Returning false from an 'onHttpException' callback allows developers to handle HTTP errors without triggering a page navigation.

--------------------------------

### Prefetching > Link Prefetching

Source: https://inertiajs.com/docs/v3/data-props/prefetching

Beyond hover-based prefetching, developers can trigger data loading on mousedown using the click strategy, or immediately upon component mount. These strategies can also be combined by providing an array of values to the prefetch configuration.

--------------------------------

### Customizing Transitions

Source: https://inertiajs.com/docs/v3/the-basics/view-transitions

Individual elements can be animated between pages by assigning them a unique `view-transition-name`. This is useful for animating specific components, such as an avatar that changes size between a profile page and a dashboard.

--------------------------------

### TypeScript > Global Configuration > Layout Props

Source: https://inertiajs.com/docs/v3/advanced/typescript

The `layoutProps` option types the data passed to `setLayoutProps()`, while `namedLayoutProps` types data for `setLayoutProps('name', props)`, keyed by layout name. This provides type checking for layout-specific data, ensuring correct prop usage for both default and named layouts.

--------------------------------

### Shared Data > Sharing Once Props

Source: https://inertiajs.com/docs/v3/data-props/shared-data

Once props allow you to share data that is resolved only once and remembered by the client across subsequent navigations. This can be configured within the `HandleInertiaRequests` middleware using `shareOnce()` methods or directly via the `Inertia::shareOnce()` static method. This is efficient for data that doesn't change frequently.

--------------------------------

### Partial Reloads

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

The `only` option allows requesting a subset of props from the server for subsequent visits, improving efficiency by only refreshing necessary data.

--------------------------------

### HTTP Helper

Source: https://inertiajs.com/docs/v3/advanced/typescript

The `useHttp` hook accepts two generic type parameters: one for the form data type and an optional one for the default response type. This enhances type safety when making HTTP requests within your application.

--------------------------------

### Demo Application

Source: https://inertiajs.com/docs/v3/getting-started/demo-application

The official Inertia.js v3 demo application is a comprehensive 'Kitchen Sink' app built with Laravel and Vue. It includes a mini CRM with contacts, organizations, and notes, along with dedicated feature showcase pages covering forms, navigation, data loading, prefetching, state management, layouts, events, error handling, and more. The source code is available on GitHub.

--------------------------------

### Options

Source: https://inertiajs.com/docs/v3/the-basics/forms

The `validate()` method accepts an options object that includes callbacks for different validation stages such as `onSuccess`, `onValidationError`, `onBeforeValidation`, and `onFinish`. You can also provide an options object without specifying a field to validate specific fields using the `only` property.

--------------------------------

### Links > Creating Links

Source: https://inertiajs.com/docs/v3/the-basics/links

By default, Inertia renders links as anchor `<a>` elements. However, you can change the rendered HTML tag by utilizing the `as` prop on the `<Link>` component.

--------------------------------

### Layouts > Defining Defaults

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Layout props can be defined as regular component props and assigned default values. This allows you to set initial states for your layout components that can be overridden if needed.

--------------------------------

### View Transitions > Links

Source: https://inertiajs.com/docs/v3/the-basics/view-transitions

The viewTransition option is also available on the Link component, allowing for animated transitions during navigation. Similar to programmatic visits, the Link component supports passing a callback to access the ViewTransition instance for more granular control over the animation lifecycle.

--------------------------------

### useHttp > Validation Errors > Displaying All Errors

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

By default, validation errors are simplified to the first error message for each field. You may chain `withAllErrors()` to receive all error messages as arrays, which is useful for fields with multiple validation rules.

--------------------------------

### Links > Method

Source: https://inertiajs.com/docs/v3/the-basics/links

You can specify the HTTP request method for an Inertia link using the `method` prop. While the default method is `GET`, you can configure it to make `POST`, `PUT`, `PATCH`, and `DELETE` requests.

--------------------------------

### Other Changes > Blade Components

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Inertia now offers Blade components as an alternative to traditional directives for managing head and app elements. The new head component allows for fallback content that only renders when SSR is inactive, which helps prevent duplicate title tags in SSR-enabled applications.

--------------------------------

### Error Handling > Production

Source: https://inertiajs.com/docs/v3/advanced/error-handling

For production environments, developers should implement custom error responses rather than relying on development-time modals. This is achieved by configuring exception handling within the application's service provider to render specific error pages based on HTTP status codes.

--------------------------------

### Manual Visits

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

The reload method is a specialized shortcut designed to refresh the current page. It automatically triggers a visit to the existing URL with both preserveState and preserveScroll enabled, making it an ideal choice for updating page data without disrupting the user experience.

--------------------------------

### Event Callbacks

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

The `onCancel()` and `onFinish()` event callbacks are executed when a visit is cancelled. These callbacks provide hooks to perform actions or cleanup when a visit is aborted.

--------------------------------

### Prop Helpers

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

Inertia provides helper methods to update page props without triggering full server requests. These methods act as shortcuts to router.replace and automatically enable preserveScroll and preserveState. They support dot notation for accessing nested properties and allow for callback functions that receive the current value and page props as arguments.

--------------------------------

### View Transitions

Source: https://inertiajs.com/docs/v3/the-basics/view-transitions

Inertia supports the View Transitions API, which enables developers to animate page transitions. This feature is a relatively new browser capability, and Inertia provides a graceful fallback to standard page transitions for browsers that do not support the API.

--------------------------------

### Creating Pages

Source: https://inertiajs.com/docs/v3/the-basics/pages

Inertia pages are standard JavaScript components compatible with Vue, React, or Svelte. These components receive data from application controllers as props, allowing for seamless integration with your existing frontend framework knowledge.

--------------------------------

### Code Splitting > Manual Vite

Source: https://inertiajs.com/docs/v3/advanced/code-splitting

For projects not using the Inertia Vite plugin, code splitting can be managed manually using Vite's import.meta.glob function. Passing the eager option allows for bundling all pages, while omitting it maintains the default lazy-loading behavior.

--------------------------------

### Endpoint Tests

Source: https://inertiajs.com/docs/v3/advanced/testing

The inertiaProps method allows you to retrieve props returned in a response. You can retrieve all props, a specific property by key, or nested properties using dot notation.

--------------------------------

### Flash Data

Source: https://inertiajs.com/docs/v3/data-props/flash-data

Flash data allows you to send temporary, one-time data to your frontend. This data is not persisted in browser history, making it suitable for transient information like success messages or newly created IDs. It is ideal for scenarios where data should only be visible for a single navigation event.

--------------------------------

### Testing Inertia > Assertions

Source: https://inertiajs.com/docs/v3/advanced/testing

The missing method is the functional opposite of the has method, providing a way to explicitly verify that a specific property does not exist in the response. It is often used alongside the etc method to define expected data boundaries.

--------------------------------

### Global Flash Event

Source: https://inertiajs.com/docs/v3/data-props/flash-data

When registering event listeners inside components, it is essential to clean them up when the component unmounts. This practice prevents listeners from accumulating and firing multiple times, which is particularly critical in non-persistent layouts.

--------------------------------

### Running the SSR Server

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Inertia provides an option to verify the existence of the runtime binary before attempting to start the SSR server. If enabled, the startup command will terminate with an error if the specified runtime binary cannot be located.

--------------------------------

### Once Props > Creating Once Props

Source: https://inertiajs.com/docs/v3/data-props/once-props

If you navigate to a page that does not include a specific once prop, the client will forget the previously remembered value. This value will then be re-resolved on the next page that requires it. In most practical scenarios, this behavior is not an issue, as once props are commonly employed for shared data or within distinct sections of an application.

--------------------------------

### TypeScript

Source: https://inertiajs.com/docs/v3/advanced/typescript

Inertia offers comprehensive TypeScript support. You can configure global types through declaration merging and utilize generics with hooks and router methods to ensure type safety for props, forms, and state management.

--------------------------------

### Customizing the App

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

The withApp callback accepts a second argument containing the ssr property. This allows developers to conditionally apply logic based on whether the application is currently being rendered on the server or in the browser.

--------------------------------

### Wayfinder Integration

Source: https://inertiajs.com/docs/v3/the-basics/instant-visits

The instant prop on the Link and Form components allows Inertia to automatically extract the target component from Wayfinder route definitions. This eliminates the need to manually specify the component prop for navigation or form submissions. If an explicit component prop is provided, it will always take priority over the instant functionality.

--------------------------------

### Loading Before Visible

Source: https://inertiajs.com/docs/v3/data-props/load-when-visible

The WhenVisible component wraps fallback content in a div element by default to track visibility. You can customize this behavior by using the as prop to specify a different HTML element to serve as the wrapper.

--------------------------------

### Form Component

Source: https://inertiajs.com/docs/v3/the-basics/optimistic-updates

The Form component supports optimistic updates through the optimistic prop. Because the component manages input data internally, the form data is provided as a second callback argument to facilitate state updates.

--------------------------------

### Loading Direction

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The infinite scroll component loads content in both directions when you scroll near the start or end. You can control this behavior using the `only-next` and `only-previous` props to specify whether to load only the next page, only the previous page, or both.

--------------------------------

### ProvidesInertiaProperties Interface

Source: https://inertiajs.com/docs/v3/the-basics/responses

Classes implementing ProvidesInertiaProperties can be used directly within render or with methods. These classes can also be combined with other props in an array or through method chaining to provide a flexible way to manage component data.

--------------------------------

### Remembering State

Source: https://inertiajs.com/docs/v3/advanced/typescript

The useRemember hook supports generic type parameters to ensure local state persistence is type-safe. This provides autocomplete functionality and guarantees that stored values adhere to the expected data structures.

--------------------------------

### Project > Resetting the Form

Source: https://inertiajs.com/docs/v3/the-basics/forms

The `Form` component offers attributes to control form resetting after submission. `resetOnSuccess` can be used to clear the entire form or specific fields upon a successful submission. Similarly, `resetOnError` can be utilized to reset the form when an error occurs.

--------------------------------

### Manual Visits

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

Inertia allows developers to manually trigger visits or requests programmatically using JavaScript. This is achieved through the router.visit method, which provides extensive configuration options for managing the request lifecycle, data handling, and state preservation.

--------------------------------

### Forms > Programmatic Access

Source: https://inertiajs.com/docs/v3/the-basics/forms

You can access the form's methods programmatically using refs. This provides an alternative to slot props when you need to trigger form actions from outside the form.

--------------------------------

### Active States

Source: https://inertiajs.com/docs/v3/the-basics/links

Active states for navigation links are managed by inspecting the page object. You can perform comparisons against the current URL or component name to determine if a link is active. This approach supports exact matches, prefix matching, or complex regular expressions, allowing for flexible conditional rendering of styles or elements.

--------------------------------

### Options

Source: https://inertiajs.com/docs/v3/the-basics/forms

By default, file inputs are excluded from validation requests to optimize performance. This behavior can be changed to enable file validation, which is useful for checking file properties like size or MIME type.

--------------------------------

### Form Helper

Source: https://inertiajs.com/docs/v3/advanced/typescript

The form helper in Inertia.js v3 accepts a generic type parameter, which enables type-safe handling of form data and errors. This feature provides autocompletion for form fields and their associated errors, while also preventing common typos in field names.

--------------------------------

### The data-inertia Attribute

Source: https://inertiajs.com/docs/v3/the-basics/title-and-meta

The value assigned to the data-inertia attribute in the Blade template must correspond to the head-key property defined on the client-side Head elements. This mapping ensures that the client-side component correctly identifies and synchronizes the intended elements.

--------------------------------

### History State

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

Data and errors can be persisted in the browser history state by providing a unique remember key. Sensitive fields can be excluded from this persistence mechanism using the dontRemember method.

--------------------------------

### URL Synchronization

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The infinite scroll component automatically updates the browser URL's query string to reflect the currently visible page of content. This synchronization works in both directions as users scroll, allowing them to bookmark or share links to specific content pages. This behavior can be disabled if the infinite scroll is used for secondary content that should not affect the main page URL.

--------------------------------

### Auto-Reset on Navigation

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Dynamic layout props are automatically reset upon navigation to ensure a clean state for each page, unless preserveState is enabled. This prevents stale data from persisting between page transitions.

--------------------------------

### Client Side Visits

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

By default, all parameters passed to `router.push` or `router.replace` (except `errorBag`) will be merged with the current page's data. This means you are responsible for overriding the current page's URL, component, and props if needed.

--------------------------------

### Flash Data > The onFlash Callback

Source: https://inertiajs.com/docs/v3/data-props/flash-data

The `onFlash` callback offers a way to handle flash data specifically when making requests. This callback allows you to process temporary data, such as updating form fields with newly created IDs, directly within your request handling logic on the frontend.

--------------------------------

### Endpoint Tests

Source: https://inertiajs.com/docs/v3/advanced/testing

The has method is used to assert that an Inertia response contains a specific property, functioning similarly to the isset function. You can also use this method to verify that a property contains a specific number of items by providing the expected size as a second argument.

--------------------------------

### Events > Registering Listeners

Source: https://inertiajs.com/docs/v3/advanced/events

Inertia utilizes native browser events for its lifecycle hooks. Because of this, developers can interact with these events using standard browser event methods by prepending 'inertia:' to the event name.

--------------------------------

### Asset Versioning

Source: https://inertiajs.com/docs/v3/advanced/asset-versioning

Inertia.js simplifies asset refreshing in single-page applications by optionally tracking the current version of your site assets. When an asset changes, Inertia automatically triggers a full page visit instead of an XHR visit on the next request, ensuring users always have the latest assets.

--------------------------------

### Layouts > Callback Props > Returning Props Only

Source: https://inertiajs.com/docs/v3/the-basics/layouts

When a default layout is configured, callbacks can return a plain props object instead of a full layout definition. Inertia will automatically use the default layout and merge the returned props onto it.

--------------------------------

### Links > Creating Links

Source: https://inertiajs.com/docs/v3/the-basics/links

To create links in an Inertia app, use the Inertia `<Link>` component. Any attributes passed to this component are proxied to the underlying HTML tag, allowing for flexible customization.

--------------------------------

### Custom

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

You can implement custom page loading indicators by leveraging Inertia events. The NProgress library is used as an example to demonstrate this process. First, disable Inertia's default loading indicator by setting `progress: false` in `createInertiaApp`.

--------------------------------

### Asset Versioning

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

Asset versioning is crucial for single-page applications to ensure users receive updated assets. Inertia uses a `version` identifier in the page object, set server-side, to track asset changes. When an asset changes, Inertia automatically triggers a full-page visit instead of an XHR visit.

--------------------------------

### Error Handling > Production

Source: https://inertiajs.com/docs/v3/advanced/error-handling

When handling exceptions that occur outside of the standard Inertia middleware, such as 404 errors, shared data and root views are not automatically available. Developers must explicitly call methods to resolve the middleware and include shared props to ensure the error page renders correctly with the expected application data.

--------------------------------

### Visit Options > Showprogress

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

The `showProgress` option allows you to precisely control whether the loading indicator is visible during a request. This can be set to `false` to disable the indicator for specific visits.

--------------------------------

### Merge Priority

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Layout props are resolved based on a specific hierarchy. Dynamic props set via setLayoutProps take the highest priority, followed by static props defined in the layout, and finally default values declared on the layout component itself.

--------------------------------

### Multiple Scroll Containers

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

When multiple infinite scroll components are present on a single page, they may conflict if they share the same default page query parameter. To prevent this, developers should assign a unique pageName to each paginator. The Inertia::scroll method automatically detects these names, allowing each container to maintain independent pagination state and generating distinct URL parameters for each list.

--------------------------------

### Form Component

Source: https://inertiajs.com/docs/v3/the-basics/forms

When using the `<Form>` component, there's no need to bind input fields with `v-model`, `onChange` handlers, or `bind:`. Simply assign a `name` attribute to each input, and the `Form` component will manage data submission automatically. For React, `defaultValue` is also relevant.

--------------------------------

### Code Splitting

Source: https://inertiajs.com/docs/v3/advanced/code-splitting

Inertia.js defaults to lazy-loading page components, which splits each page into its own bundle loaded on demand. This approach reduces the initial JavaScript bundle size but requires additional network requests when navigating to new pages.

--------------------------------

### Repopulating Input

Source: https://inertiajs.com/docs/v3/the-basics/validation

Inertia automatically preserves component state for post, put, patch, and delete requests when validation errors occur. This means that old form input data remains intact when a user is redirected back to the form page, eliminating the need for manual repopulation of form fields.

--------------------------------

### Touch and Validate

Source: https://inertiajs.com/docs/v3/the-basics/forms

The touch system allows developers to mark specific fields as interacted with without triggering immediate validation. By using the touch method, you can track user interaction and subsequently validate only those fields that have been touched. The system also provides helpers to check the touched status of a field and to clear that state when necessary.

--------------------------------

### Instant Visits > Basic Usage

Source: https://inertiajs.com/docs/v3/the-basics/instant-visits

To make an instant visit, provide the target component name to a Link or to router.visit(). When clicked, Inertia immediately renders the target component while the server request fires in the background. The full props are merged in when the response arrives.

--------------------------------

### ProvidesInertiaProperties Interface

Source: https://inertiajs.com/docs/v3/the-basics/responses

The ProvidesInertiaProperties interface is designed for grouping related props together to improve reusability across different pages. Implementing this interface requires a toInertiaProperties method that returns an array of key-value pairs, receiving a RenderContext object that includes the component name and request instance.

--------------------------------

### Infinite Scroll > Server-Side

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

To configure paginated data for infinite scrolling on the server-side, use the `Inertia::scroll()` method when returning a response. This method automatically handles the necessary merge behavior and normalizes pagination metadata for the frontend component. It supports Laravel's `paginate()`, `simplePaginate()`, and `cursorPaginate()` methods, as well as pagination data wrapped in Eloquent API resources.

--------------------------------

### Form Processing Reset Timing

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

The useForm helper has been modified to reset processing and progress states only upon the completion of the onFinish callback. This change ensures that the processing state accurately reflects the status of the visit until it has fully concluded.

--------------------------------

### Asset Versioning

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

Inertia includes the current asset version in the `X-Inertia-Version` header for every request. The server compares this with its current asset version. If they differ, a `409 Conflict` response is sent with an `X-Inertia-Location` header pointing to the correct URL, prompting Inertia to perform a full-page visit.

--------------------------------

### Once Props > Custom Keys

Source: https://inertiajs.com/docs/v3/data-props/once-props

You have the flexibility to assign a custom key to a once prop using the `as()` method. This feature is particularly useful when you intend to share the same underlying data across multiple pages but wish to use different prop names for each. By using a custom key, both pages will reference the same data, and the prop will only be resolved for the first page visited that utilizes that key.

--------------------------------

### Prerequisites

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

Inertia requires a client-side framework and its corresponding Vite plugin to be installed and configured. If your application already has these dependencies set up, you may skip the initial configuration steps.

--------------------------------

### Reactive State

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

The useHttp hook provides several reactive properties to monitor the lifecycle of a request. These include tracking validation errors, request progress, and success states. These properties allow components to reactively update the user interface based on the current status of the data submission.

--------------------------------

### Global Visit Options

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

You can configure a global visitOptions callback during the initialization of your Inertia application. This callback allows you to intercept every request, providing access to the target URL and current options, and enables you to return an object that overrides specific visit settings globally.

--------------------------------

### Forms > Resetting the Form

Source: https://inertiajs.com/docs/v3/the-basics/forms

To simultaneously reset form fields to their default values and clear any existing validation errors, use the `resetAndClearErrors()` method. This method also accepts specific field names to target individual fields for reset and error clearing.

--------------------------------

### Reloading Indicator

Source: https://inertiajs.com/docs/v3/data-props/deferred-props

The `reloading` prop is initially `false` during the first load. It transitions to `true` when a partial reload is in progress for deferred keys and reverts to `false` once the reload is complete.

--------------------------------

### Deferred Props > Multiple Deferred Props

Source: https://inertiajs.com/docs/v3/data-props/deferred-props

When multiple deferred props are required for a component to render, the Deferred component allows specifying an array of data keys. This ensures that all listed props are available before the component proceeds with rendering.

--------------------------------

### Testing Inertia > Testing Flash Data

Source: https://inertiajs.com/docs/v3/advanced/testing

Flash data in Inertia responses can be tested using the hasFlash and missingFlash methods. These methods support dot notation for accessing nested values and allow for verifying both the existence and the specific content of flash messages.

--------------------------------

### Progress Indicators > Default

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

The default progress indicator is a wrapper around the NProgress library and can be customized or disabled via the progress property in the createInertiaApp function. Configuration options include setting a delay before the bar appears, changing the color, toggling default CSS, and showing or hiding the spinner.

--------------------------------

### Changes > Progress Indicator Exports Removed

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

The named exports 'hideProgress()' and 'revealProgress()' have been removed. Developers should now interact with the progress indicator by using the 'progress' object directly.

--------------------------------

### Other Changes > Middleware Priority

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Inertia middleware is now automatically included in Laravel's middleware priority list. This ensures it executes before rate-limiting middleware, preventing incorrect redirect behaviors on specific HTTP methods and ensuring consistent request handling.

--------------------------------

### Loading Before Visible

Source: https://inertiajs.com/docs/v3/data-props/load-when-visible

The buffer prop allows you to initiate data loading before an element enters the viewport. By providing a numeric value representing pixels, you can trigger the loading process early to ensure content is ready by the time the user scrolls to it.

--------------------------------

### Fetching State

Source: https://inertiajs.com/docs/v3/data-props/load-when-visible

The WhenVisible component provides a fetching slot prop that allows developers to display loading indicators during subsequent data refreshes. While the fallback content is reserved for the initial load, the fetching state is specifically designed to signal that data is being updated in the background.

--------------------------------

### Form Helper

Source: https://inertiajs.com/docs/v3/data-props/remembering-state

Sensitive fields such as passwords can be excluded from automatic state persistence using the dontRemember method. This prevents sensitive information from being stored in the browser's history state.

--------------------------------

### Configuring Defaults

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

The `visitOptions` callback within the `defaults` object allows you to override global visit options. This callback receives the target URL and current visit options, and should return an object with any modifications you wish to apply.

--------------------------------

### Endpoint Tests

Source: https://inertiajs.com/docs/v3/advanced/testing

To verify that an Inertia property matches an expected value, you can use the where assertion. This ensures that the data returned in the response matches your specified criteria.

--------------------------------

### Testing Inertia > Testing Flash Data > Redirect Responses

Source: https://inertiajs.com/docs/v3/advanced/testing

For redirect responses, where standard page rendering does not occur, you can use the assertInertiaFlash and assertInertiaFlashMissing methods directly on the test response. These methods allow you to verify session flash data associated with the redirect.

--------------------------------

### Updating Configuration at Runtime

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

You can update configuration values at runtime using the exported config instance. This functionality is useful for adjusting settings dynamically based on user preferences or the current state of the application.

--------------------------------

### Shared Data > TypeScript

Source: https://inertiajs.com/docs/v3/data-props/shared-data

You can configure the shared props type globally using TypeScript's declaration merging feature, allowing for better type safety and autocompletion in your shared data.

--------------------------------

### Infinite Scroll

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

Inertia's infinite scroll feature loads additional pages of content as users scroll, replacing traditional pagination controls. This is ideal for applications such as chat interfaces, social feeds, photo grids, and product listings.

--------------------------------

### Element Targeting

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

You can define custom trigger elements for loading more content by providing CSS selectors. This approach replaces the default trigger elements with intersection observers attached to your specified elements. Alternatively, you can use template refs instead of CSS selectors to provide direct element references, which avoids the need to add specific HTML attributes to your elements.

--------------------------------

### Server-Side Responses

Source: https://inertiajs.com/docs/v3/the-basics/forms

Inertia applications typically handle form submissions by redirecting the user after the server processes the request, rather than inspecting responses client-side. This approach mirrors traditional server-side form handling and is compatible with all standard form submission methods, including the Form component and the useForm helper.

--------------------------------

### Progress Indicators

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

Inertia provides a built-in progress indicator to compensate for the lack of browser loading states during XHR-based navigation. This indicator appears automatically for standard Inertia visits, though it is not shown for asynchronous requests unless explicitly configured.

--------------------------------

### Polling

Source: https://inertiajs.com/docs/v3/data-props/polling

You can pass additional request options to the poll helper, such as `onStart` and `onFinish` callbacks, by providing them as the second parameter. These options are the same as those available for `router.reload`.

--------------------------------

### Instant Visits > Shared Props

Source: https://inertiajs.com/docs/v3/the-basics/instant-visits

The Laravel adapter includes a `sharedProps` metadata key in the page response, listing the top-level prop keys registered via `Inertia::share()`. Inertia reads this list and carries those props over from the current page to the intermediate page. Props like `auth` are available immediately, while page-specific props like `stats` will be `undefined` until the server responds.

--------------------------------

### Infinite Scroll > Loading Buffer

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

You can control the loading buffer for infinite scroll to determine how early content begins loading. The buffer specifies the distance in pixels from the end of the content where loading should commence. A larger buffer loads content earlier but might load content that users never see.

--------------------------------

### Demo Application

Source: https://inertiajs.com/docs/v3/getting-started/demo-application

The demo application is hosted on Laravel Cloud and its database is reset every midnight. Users are asked to be respectful when editing data.

--------------------------------

### Authentication

Source: https://inertiajs.com/docs/v3/security/authentication

Developers can utilize the native session-based authentication systems provided by their server-side framework. This allows for seamless integration with existing authentication workflows, such as those found in Laravel.

--------------------------------

### How It Works > Use the Tools You Love

Source: https://inertiajs.com/docs/v3/core-concepts/how-it-works

Inertia allows developers to build applications using their existing server-side frameworks for routing, controllers, and authentication. It replaces the traditional server-side view layer with JavaScript page components, enabling the use of frontend frameworks like React, Vue, or Svelte while maintaining server-side productivity.

--------------------------------

### Scroll Preservation

Source: https://inertiajs.com/docs/v3/the-basics/links

Use the `preserveScroll` prop to prevent Inertia from automatically resetting the scroll position during page visits.

--------------------------------

### Manual Setup

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

The Vite plugin defaults to using your `app.js` entry point for SSR, eliminating the need for a separate file in many cases. For more advanced control, such as providing a manual `setup` callback, you can create a dedicated `resources/js/ssr.js` file.

--------------------------------

### Method

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

Uploading files via `put` or `patch` is not supported in Laravel. Instead, make the request via `post`, including a `_method` field set to `put` or `patch`. This is called form method spoofing.

--------------------------------

### Asset Versioning > Configuration

Source: https://inertiajs.com/docs/v3/advanced/asset-versioning

To enable automatic asset refreshing, you must inform Inertia about the current version of your assets. This version can be any arbitrary string, such as letters, numbers, or a file hash, as long as it changes whenever your assets are updated. Typically, this is configured within the `version` method of the Inertia `HandleInertiaRequests` middleware.

--------------------------------

### Server-Side Rendering (SSR) > Deployment

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

When deploying an SSR-enabled application, you must build both the client-side and server-side bundles. The SSR server should then be run as a background process, managed by a process monitoring tool like Supervisor. Artisan commands are available to start, stop, and check the status of the SSR server.

--------------------------------

### File Uploads > FormData Conversion

Source: https://inertiajs.com/docs/v3/the-basics/file-uploads

When Inertia requests include files, the request data is automatically converted into a FormData object. This conversion is required to successfully submit a multipart/form-data request via XHR. Developers can also force the use of FormData for any request by providing the forceFormData option.

--------------------------------

### Forms > Non-Inertia Submissions

Source: https://inertiajs.com/docs/v3/the-basics/forms

For standalone HTTP requests that do not require page visits, Inertia offers the `useHttp` hook, providing a similar developer experience to `useForm`. Alternatively, you can use any preferred library for making plain XHR or `fetch` requests.

--------------------------------

### ProvidesInertiaProperty Interface

Source: https://inertiajs.com/docs/v3/the-basics/responses

The ProvidesInertiaProperty interface allows for context-aware transformations of data passed to components. By implementing this interface, classes can define a toInertiaProperty method that receives a PropertyContext object, providing access to the property key, all page props, and the current request instance.

--------------------------------

### Custom > Handling Cancelled Visits

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

To handle cancelled visits gracefully, inspect the `event.detail.visit` object in the 'finish' event listener. This allows you to reset the progress bar for interrupted visits or remove it entirely for manually cancelled visits.

--------------------------------

### Global Flash Event

Source: https://inertiajs.com/docs/v3/data-props/flash-data

The global flash event allows developers to handle flash data in a centralized location, such as a layout component. This event is not cancelable and triggers on every response that contains flash data.

--------------------------------

### State Preservation

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

By default, navigating to the same page in Inertia creates a fresh component instance, which resets local state like form inputs, scroll positions, and focus. To prevent this, developers can use the preserveState option. Methods such as post, put, patch, delete, and reload enable this by default, while the get method requires explicit configuration.

--------------------------------

### Targeting Named Layouts

Source: https://inertiajs.com/docs/v3/the-basics/layouts

Nested layouts can be defined as a named object rather than an array. This structure allows you to target specific layouts by name when updating props, providing more granular control over complex layout hierarchies.

--------------------------------

### Custom > File Upload Progress

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

For file uploads, you can update the loading indicator to reflect the upload progress using the 'progress' event. The `NProgress.set()` method can be used with the upload percentage to show real-time progress, typically capped at 90% to allow for server response time.

--------------------------------

### Who Is Inertia.js For?

Source: https://inertiajs.com/docs/v3/core-concepts/who-is-it-for

Building a traditional Single Page Application (SPA) often necessitates creating a REST or GraphQL API, managing API authentication and authorization, implementing client-side state management, setting up new code repositories, and adopting more complex deployment strategies. Inertia.js offers an alternative approach to avoid this paradigm shift and its associated complexities.

--------------------------------

### Remembering State > Saving Local State

Source: https://inertiajs.com/docs/v3/data-props/remembering-state

To address the issue of lost local component state during history navigation, Inertia provides the `useRemember` feature. This allows you to specify which local component data should be saved and restored automatically.

--------------------------------

### History Encryption > How It Works

Source: https://inertiajs.com/docs/v3/security/history-encryption

Inertia.js provides a history encryption feature to prevent users from viewing privileged information after logging out by pressing the browser back button. This feature encrypts the current page's data before pushing it to the history state, storing the decryption key in the browser's session storage. When a user navigates back, the data is decrypted using this key. This functionality relies on the browser's crypto API and requires a secure environment with SSL enabled.

--------------------------------

### Form Context

Source: https://inertiajs.com/docs/v3/the-basics/forms

The `useFormContext` hook allows deeply nested child components to access the state and methods of a parent `<Form>` component without prop drilling. This hook provides a convenient way to interact with form data and actions from anywhere within the form's component tree.

--------------------------------

### Wayfinder

Source: https://inertiajs.com/docs/v3/the-basics/forms

When using the Wayfinder library with the Inertia form helper, you can pass the Wayfinder object directly to the form submission method. The form helper automatically infers the necessary HTTP method and URL from the provided object.

--------------------------------

### How It Works

Source: https://inertiajs.com/docs/v3/the-basics/optimistic-updates

Optimistic update callbacks should return a partial object containing only the specific keys intended for update. These returned values are then shallow-merged with the existing data.

--------------------------------

### File Uploads

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

Inertia automatically converts request data into a FormData object when files are included in a visit. If you need to ensure that a request always utilizes a FormData object regardless of file presence, you can explicitly set the forceFormData option to true.

--------------------------------

### Client Side Visits

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

If you need access to the current page's props when making a visit, you can pass a function to the `props` option. This function receives the current props and should return the new props. The function also receives any 'once' props as a second argument, which is useful for preserving them while replacing regular props.

--------------------------------

### CSRF Protection > Making Requests

Source: https://inertiajs.com/docs/v3/security/csrf-protection

For frameworks that require manual CSRF protection, you must include the necessary token for POST, PUT, PATCH, and DELETE requests. One manual approach involves passing the CSRF token as a prop on every response or utilizing shared data to automatically include it.

--------------------------------

### Advanced Data Loading > Lazy Data Evaluation

Source: https://inertiajs.com/docs/v3/data-props/partial-reloads

For partial reloads to be most effective, use lazy data evaluation when returning props from server-side routes or controllers. Wrap optional page data in a closure, and Inertia will evaluate the closure only when the data is required, significantly improving performance for pages with substantial optional data.

--------------------------------

### How It Works > Intercepting Requests

Source: https://inertiajs.com/docs/v3/core-concepts/how-it-works

Inertia functions as a client-side routing library that enables page transitions without full browser reloads. By using the Link component or programmatic visits, Inertia intercepts navigation requests and performs them via XHR. This prevents the client-side framework from rebooting on every page load, resulting in a seamless single-page application experience.

--------------------------------

### Infinite Scroll > Custom Element

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

The InfiniteScroll component defaults to rendering as a div element. You can change the underlying HTML element by using the as prop, which allows for better semantic structure when rendering lists or other specific content types.

--------------------------------

### CSRF Protection > Making Requests

Source: https://inertiajs.com/docs/v3/security/csrf-protection

Inertia provides built-in XSRF token handling by automatically checking for an XSRF-TOKEN cookie and including it in an X-XSRF-TOKEN header for requests. This is best implemented via server-side middleware that sets the cookie and verifies the header.

--------------------------------

### Once Props > Sharing Once Props

Source: https://inertiajs.com/docs/v3/data-props/once-props

You can define once props globally within your middleware by implementing a dedicated `shareOnce()` method. This method will be evaluated alongside the standard `share()` method, and their results will be merged, allowing for centralized management of globally shared once props.

--------------------------------

### Forms > Form Field Change Tracking

Source: https://inertiajs.com/docs/v3/the-basics/forms

The `isDirty` property can be used to determine if a form has any unsaved changes. This is useful for prompting users before they navigate away from a page with unsubmitted data.

--------------------------------

### Remembering State

Source: https://inertiajs.com/docs/v3/data-props/remembering-state

Inertia restores pages using prop data cached in history state when navigating browser history. However, it does not restore local page component state, which can lead to outdated pages. For instance, a partially completed form might be reset when a user navigates back, causing their work to be lost.

--------------------------------

### View Transitions > Transition Callbacks

Source: https://inertiajs.com/docs/v3/the-basics/view-transitions

Developers can pass a callback to the viewTransition option to access the browser's ViewTransition instance. This allows for hooking into specific promises provided by the API, such as when the transition is ready, when the DOM has been updated, or when the transition has finished.

--------------------------------

### Routing > Generating URLs > Wayfinder

Source: https://inertiajs.com/docs/v3/the-basics/routing

Wayfinder allows developers to pass generated TypeScript methods directly to Inertia components and helpers. This integration is pre-configured in Laravel starter kits and simplifies handling routes in a type-safe manner.

--------------------------------

### Cancelling Requests

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

In-progress HTTP requests can be terminated at any time using the cancel method.

--------------------------------

### Responses > Properties

Source: https://inertiajs.com/docs/v3/the-basics/responses

Properties are used to pass data from the server to your page components. You can pass various data types, including primitives, arrays, objects, and specific Laravel types. Arrayable objects like Eloquent models and collections are automatically converted using their `toArray()` method, while Responsable objects like API resources are resolved through their `toResponse()` method.

--------------------------------

### Slot Props > defaults method

Source: https://inertiajs.com/docs/v3/the-basics/forms

The defaults method updates the form's default values to match the current field values. Subsequent calls to reset will restore fields to these new defaults, and the isDirty property will track changes relative to these updated values.

--------------------------------

### Form Submissions

Source: https://inertiajs.com/docs/v3/data-props/load-when-visible

When submitting forms, it is often beneficial to use the except option to exclude props managed by the WhenVisible component. This prevents unnecessary reloading of those specific props if the application redirects back to the current page due to validation errors.

--------------------------------

### Prefetching

Source: https://inertiajs.com/docs/v3/data-props/once-props

When using prefetching, the client automatically includes remembered once props in prefetched responses. This ensures that navigating to a prefetched page provides immediate access to these props. If a prefetched page contains an expired once prop, it is automatically invalidated from the cache.

--------------------------------

### Cache Tags

Source: https://inertiajs.com/docs/v3/data-props/prefetching

Cache tags enable grouping of related prefetched data. You can assign tags to cached data using the `cacheTags` prop on the `Link` component or programmatically via `router.prefetch`. This allows for targeted cache invalidation.

--------------------------------

### Vite Plugin Setup

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

The recommended method for configuring SSR is by using the `@inertiajs/vite` plugin. This plugin simplifies SSR setup by handling configurations automatically, including development mode SSR without requiring a separate Node.js server.

--------------------------------

### Asset Versioning

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

Note that `409 Conflict` responses are exclusively sent for `GET` requests. However, they can occur after `POST`, `PUT`, `PATCH`, or `DELETE` requests if a `GET` redirect follows.

--------------------------------

### Events > Progress

Source: https://inertiajs.com/docs/v3/advanced/events

The `progress` event is emitted as the upload progress increases during file uploads. This event is not cancelable and can be used to display upload progress to the user.

--------------------------------

### Routing > Generating URLs > Ziggy

Source: https://inertiajs.com/docs/v3/the-basics/routing

The Ziggy library provides a global route function that makes server-side named routes available to client-side code. It includes specific support for Vue templates and can be configured to support server-side rendering environments.

--------------------------------

### Transforming Component Names

Source: https://inertiajs.com/docs/v3/the-basics/pages

Component names can be transformed on either the client side or the server side. This transformation is useful for customizing how component paths are resolved, and the resulting name is used for existence checks.

--------------------------------

### Code Splitting > Webpack

Source: https://inertiajs.com/docs/v3/advanced/code-splitting

To implement code splitting with Webpack, dynamic imports must be enabled via a Babel plugin. Projects using Laravel Mix 6 or higher have this configuration pre-installed, while other setups require manual installation and configuration of the dynamic import plugin.

--------------------------------

### Partial Reloads > Router Shorthand

Source: https://inertiajs.com/docs/v3/data-props/partial-reloads

Since partial reloads are typically made to the same page component, the `router.reload()` method is a convenient shorthand. It automatically uses the current URL and allows you to specify `only` or `except` options.

--------------------------------

### HTTP Requests

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

Unlike router visits, `useHttp` requests do not trigger page navigation or interact with Inertia's page lifecycle. These are plain HTTP requests that return JSON responses, making them suitable for fetching data independently.

--------------------------------

### Advanced Data Loading > Lazy Data Evaluation

Source: https://inertiajs.com/docs/v3/data-props/partial-reloads

Inertia provides `Inertia::optional()` to specify props that should never be included unless explicitly requested using the `only` option. Conversely, `Inertia::always()` ensures a prop is always included, even if not explicitly requested during a partial reload.

--------------------------------

### Event Callbacks

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

Returning `false` from the `onHttpException()` or `onNetworkError()` callbacks will prevent the corresponding global event from firing and executing its default behavior. This allows for custom handling of errors.

--------------------------------

### Checkbox Inputs

Source: https://inertiajs.com/docs/v3/the-basics/forms

When using checkboxes, it is recommended to provide an explicit value attribute, such as value="1". If a value attribute is omitted, checked checkboxes will submit with a value of "on", which may cause issues with server-side validation rules expecting boolean values.

--------------------------------

### Testing Concerns Removed

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Several deprecated testing traits, including Has, Matching, and Debugging, have been removed. These were previously replaced by the AssertableInertia class, and most applications will not require any changes unless they were referencing these specific traits directly.

--------------------------------

### Custom > Loading Indicator Delay

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

Implement a loading indicator delay to prevent the indicator from appearing on very quick page visits. Use `setTimeout` to delay the start of the progress bar and `clearTimeout` in the 'finish' and 'progress' event listeners to cancel the timeout if the visit completes or progresses before the delay elapses.

--------------------------------

### Creating Pages

Source: https://inertiajs.com/docs/v3/the-basics/pages

To render an Inertia page, you return an Inertia response from your controller or route. If you attempt to render a page that does not exist, you can enable the inertia.ensure_pages_exist configuration option to throw an exception, which helps prevent rendering blank screens.

--------------------------------

### Custom Headers

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

The headers Inertia uses internally to communicate its state to the server take priority and therefore cannot be overwritten.

--------------------------------

### Code Splitting > Webpack

Source: https://inertiajs.com/docs/v3/advanced/code-splitting

When using Webpack, it is recommended to implement cache busting to ensure browsers load the most recent version of assets. This is achieved by configuring the output chunk filename to include a chunk hash.

--------------------------------

### Instant Visits > Basic Usage

Source: https://inertiajs.com/docs/v3/the-basics/instant-visits

The target component must be able to render without its page-specific props, as only shared props are available on the intermediate page. You may use optional chaining or conditional rendering to handle missing props.

--------------------------------

### Default Values

Source: https://inertiajs.com/docs/v3/the-basics/forms

You can set default values for form inputs using standard HTML attributes. For text inputs and textareas, use defaultValue or value depending on the framework. For checkboxes and radios, use defaultChecked or checked to define their initial state.

--------------------------------

### ESM-Only Packages

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

All Inertia packages are now distributed exclusively as ES Modules. Support for CommonJS require imports has been removed, requiring developers to transition to standard import statements.

--------------------------------

### Event Callbacks

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

For more granular control over visits, you can use cancel tokens. Inertia automatically generates a cancel token and provides it through the `onCancelToken()` callback before initiating a visit. This allows you to manually cancel individual visits if needed.

--------------------------------

### Customizing the App

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

In Vue, the withApp callback receives the app instance, enabling the use of methods like app.use, app.provide, and app.component. In React, the callback receives the app element and must return a new element, which is ideal for wrapping the application in context providers. In Svelte, the callback receives a Map that serves as the component context, allowing values to be set and accessed via getContext.

--------------------------------

### Partial Reloads

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

Inertia manages partial reloads using specific HTTP headers. The X-Inertia-Partial-Component header identifies the target component, while X-Inertia-Partial-Data and X-Inertia-Partial-Except define which props should be included or excluded. If both inclusion and exclusion headers are present, the exclusion header takes precedence.

--------------------------------

### Endpoint Tests

Source: https://inertiajs.com/docs/v3/advanced/testing

Endpoint testing involves making requests to your application and examining the responses to ensure they are correct. While standard HTTP testing tools are available, the Inertia Laravel adapter provides additional utilities specifically designed to simplify testing Inertia responses.

--------------------------------

### Shared Data > Sharing Data

Source: https://inertiajs.com/docs/v3/data-props/shared-data

Inertia's server-side adapters provide a method for making shared data available for every request, typically configured outside of controllers. In Laravel, this is handled by the `HandleInertiaRequests` middleware. Shared data can be set synchronously or lazily using closures. It's recommended to use shared data sparingly as it's included with every response.

--------------------------------

### Form Component

Source: https://inertiajs.com/docs/v3/the-basics/forms

You can modify form data before it's submitted by utilizing the `transform` prop. This prop accepts a function that allows for data manipulation, such as adding new fields or altering existing ones, though hidden inputs can also achieve similar results.

--------------------------------

### Partial Reloads > Using Links

Source: https://inertiajs.com/docs/v3/data-props/partial-reloads

Partial reloads can also be performed using Inertia links by specifying the `only` property directly on the `Link` component.

--------------------------------

### Error Handling > Development

Source: https://inertiajs.com/docs/v3/advanced/error-handling

Inertia improves the development experience by intercepting non-Inertia responses during XHR requests. Instead of requiring developers to inspect the network tab for server-side errors, Inertia displays these errors in a modal, allowing for the same formatted stack trace reporting typically seen in standard server-side frameworks.

--------------------------------

### Blade Directives

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

Prior to v3, Inertia used `@inertiaHead` and `@inertia` Blade directives. While still supported, these directives do not offer SSR fallback content. The newer Blade components are recommended for new applications.

--------------------------------

### Merging Props > Deep Merge

Source: https://inertiajs.com/docs/v3/data-props/merging-props

Instead of specifying which nested paths should be merged, you may use `Inertia::deepMerge()` to ensure a deep merge of the entire structure. This method was introduced before `Inertia::merge()` had support for prepending and targeting nested paths. In most cases, `Inertia::merge()` with its append and prepend methods should be sufficient.

--------------------------------

### Partial Reloads > Only Certain Props

Source: https://inertiajs.com/docs/v3/data-props/partial-reloads

To perform a partial reload, use the `only` visit option. This option takes an array of keys corresponding to the props you want the server to return.

--------------------------------

### TypeScript > Global Configuration > Error Values

Source: https://inertiajs.com/docs/v3/advanced/typescript

By default, validation error values are typed as `string`. You may configure TypeScript to expect arrays instead for multiple errors per field.

--------------------------------

### Prefetching > Link Prefetching

Source: https://inertiajs.com/docs/v3/data-props/prefetching

By default, Inertia prefetches data when a user hovers over a link for more than 75ms. This hover delay can be customized globally through application defaults.

--------------------------------

### Combining with Once Props

Source: https://inertiajs.com/docs/v3/data-props/deferred-props

You can chain the `once()` modifier to a deferred prop to ensure that the data is resolved only one time and is then remembered by the client across subsequent navigations.

--------------------------------

### Head Component > Server-Side Head Elements > SSR Fallback

Source: https://inertiajs.com/docs/v3/the-basics/title-and-meta

The `<x-inertia::head>` Blade component accepts fallback content via its slot. This content is rendered in the initial HTML only when SSR is not active. When SSR is active, the client-side `<Head>` component provides these elements, so the fallback is skipped.

--------------------------------

### Wayfinder

Source: https://inertiajs.com/docs/v3/the-basics/forms

When using Wayfinder, you can pass the resulting object directly to the action prop of the Form component. The component will automatically infer the necessary HTTP method and URL from the provided Wayfinder object.

--------------------------------

### Asset Versioning

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

If a `409 Conflict` response is received due to mismatched asset versions, Inertia checks for the `X-Inertia-Location` header. If present, Inertia performs a full-page visit to the specified URL, ensuring the user loads the latest assets. Any existing "flash" session data is also automatically re-flashed.

--------------------------------

### Forms

Source: https://inertiajs.com/docs/v3/the-basics/forms

The `wasSuccessful` property indicates that a form has been successfully submitted. Additionally, `recentlySuccessful` is set to `true` for two seconds after a successful submission, which can be used for temporary success messages.

--------------------------------

### Always Trigger

Source: https://inertiajs.com/docs/v3/data-props/load-when-visible

By default, the WhenVisible component triggers data loading only once upon initial visibility. Enabling the always prop allows the component to trigger data loading every time the element becomes visible, which is useful for scenarios like infinite scrolling. If a request is already in progress, the component will wait for it to complete before initiating a new request while the element remains in the viewport.

--------------------------------

### The Page Object > Page Object with Deferred Props

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

Deferred props allow for client-side lazy loading. When utilized, the page object includes a configuration that specifies which props are deferred, meaning they are not included in the initial request but are loaded subsequently.

--------------------------------

### Redirects

Source: https://inertiajs.com/docs/v3/the-basics/redirects

When making a non-GET Inertia request manually or via a `<Link>` element, you should ensure that you always respond with a proper Inertia redirect response. For example, if your controller is creating a new user, your "store" endpoint should return a redirect back to a standard `GET` endpoint, such as your user "index" page. Inertia will automatically follow this redirect and update the page accordingly.

--------------------------------

### Manual Setup

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

By default, Inertia.js lazy-loads page components, which results in each page being split into its own bundle. This approach optimizes initial load times. If you need to bundle all pages into a single file, you can configure code splitting accordingly.

--------------------------------

### TypeScript > Global Configuration > Layout Props

Source: https://inertiajs.com/docs/v3/advanced/typescript

You can achieve ad-hoc typing for layout props without modifying the global `InertiaConfig` by passing a generic type parameter directly to `setLayoutProps`. This allows for inline type definitions for specific calls to `setLayoutProps` or `setLayoutProps` with a named layout.

--------------------------------

### ES2022 Build Target

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Inertia packages have updated their build target to ES2022. Applications requiring support for older browsers that do not support this standard should utilize the Vite legacy plugin.

--------------------------------

### Error Handling

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Inertia handles SSR failures by gracefully falling back to client-side rendering. Detailed error logs are provided in the console, including component names and source locations, with specific guidance for common issues like browser API usage or incorrect file paths. Additionally, an SsrRenderFailed event is dispatched on the server, allowing developers to integrate custom logging or error tracking services.

--------------------------------

### Installation

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

To begin, install the Inertia server-side adapter using Composer. This package provides the necessary server-side functionality for Inertia.

--------------------------------

### Routing > Defining Routes

Source: https://inertiajs.com/docs/v3/the-basics/routing

In Inertia, all application routes are defined server-side, eliminating the need for client-side routers like Vue Router or React Router. Developers define standard server-side routes and return Inertia responses directly from them.

--------------------------------

### Flash Data

Source: https://inertiajs.com/docs/v3/data-props/shared-data

Flash data is designed for one-time notifications such as toast messages or success alerts. Unlike shared data, flash data is not persisted in the browser's history state, ensuring that messages do not reappear when a user navigates back or forward through their history.

--------------------------------

### Multiple Requests

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

Each useHttp instance maintains its own independent reactive state, including processing status and error tracking. To prevent state collisions when performing multiple concurrent or independent operations, developers should create separate instances for each request.

--------------------------------

### Testing Inertia > Testing Partial Reloads

Source: https://inertiajs.com/docs/v3/advanced/testing

The reloadOnly and reloadExcept methods allow you to test how an application responds to partial reloads. These methods trigger a follow-up request, enabling you to make assertions against the updated response data. You can pass either a single prop name or an array of props to these methods.

--------------------------------

### Dynamic Props

Source: https://inertiajs.com/docs/v3/the-basics/layouts

You can update layout props dynamically from any page component using the setLayoutProps function. This allows for flexible updates to layout-specific data directly from the page level.

--------------------------------

### Once Props

Source: https://inertiajs.com/docs/v3/data-props/once-props

Once props are a mechanism in Inertia.js for handling data that is infrequently updated, computationally expensive, or large. Instead of including this data in every server response, once props are remembered by the client and reused across subsequent pages that reference the same prop. This makes them particularly suitable for shared data that doesn't need to be fetched with every navigation.

--------------------------------

### Server-Side Rendering (SSR)

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Server-side rendering pre-renders your JavaScript pages on the server. This allows visitors to receive fully rendered HTML upon visiting your application, which also makes it easier for search engines to index your site.

--------------------------------

### Combining with Once Props

Source: https://inertiajs.com/docs/v3/data-props/merging-props

The once modifier can be chained onto a merge prop to ensure that the data is resolved only once. Once resolved, the client remembers this data across subsequent navigations.

--------------------------------

### Client-Side Flash

Source: https://inertiajs.com/docs/v3/data-props/flash-data

Flash data can be managed on the client side without requiring a server request by using the router flash method. New values provided through this method are merged with any existing flash data.

--------------------------------

### Client-Side Hydration

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Client-side hydration is a critical step when using SSR. By updating your application to use hydration instead of standard rendering, the framework can attach to the existing server-rendered HTML. This process makes the page interactive without requiring a full re-render of the DOM, which improves performance and user experience.

--------------------------------

### Wayfinder Integration > Conditional Components

Source: https://inertiajs.com/docs/v3/the-basics/instant-visits

When a controller action conditionally renders different components, the instant prop may not be able to determine the correct target. In these cases, you can use the withComponent method on the Wayfinder route function to explicitly define the target component for the visit.

--------------------------------

### Props and Options

Source: https://inertiajs.com/docs/v3/the-basics/forms

When the `disableWhileProcessing` prop is set on the `Form` component, it adds the `inert` attribute to the HTML `form` tag during processing. This attribute prevents user interaction with the form while it's submitting.

--------------------------------

### Responses > Creating Responses

Source: https://inertiajs.com/docs/v3/the-basics/responses

To create an Inertia response, use the `Inertia::render()` method. This method takes the name of the JavaScript page component and any properties (data) you want to pass to it. The component name can also be a Backed Enum for better organization and type safety.

--------------------------------

### Forms > Form Errors

Source: https://inertiajs.com/docs/v3/the-basics/forms

When errors are manually set on a form instance using `setError()`, the page's props remain unchanged, unlike a typical form submission.

--------------------------------

### Server-Side Rendering (SSR) > Laravel Starter Kits

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

If you are using Laravel Starter Kits, Inertia SSR is supported through a build command: `npm run build:ssr`.

--------------------------------

### Advanced Data Loading > Preserving Errors

Source: https://inertiajs.com/docs/v3/data-props/partial-reloads

Errors are shared using `Inertia::always()`, meaning they are included in every response. To prevent an empty error bag from overwriting existing client-side errors during partial reloads where no validation occurs, use the `preserveErrors` option.

--------------------------------

### Prefetching > Link Prefetching

Source: https://inertiajs.com/docs/v3/data-props/prefetching

Prefetched data is cached for 30 seconds by default before being evicted. This duration can be adjusted globally in application defaults or overridden on a per-link basis.

--------------------------------

### Redirects > Preserving Fragments

Source: https://inertiajs.com/docs/v3/the-basics/redirects

You may preserve the fragment from the original request during a redirect by chaining the `preserveFragment` method on the redirect response. The client will carry over the fragment to the redirect target, ensuring the user lands on the correct section of the page.

--------------------------------

### LazyProp Removed

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

The LazyProp class and the Inertia::lazy method have been removed in favor of Inertia::optional. This change completes the deprecation process started in previous versions and provides a consistent way to handle optional data props.

--------------------------------

### Validation > How It Works

Source: https://inertiajs.com/docs/v3/the-basics/validation

Inertia handles validation by mimicking standard full-page form submissions rather than using XHR-driven 422 responses. When validation fails, the server redirects the user back to the form page and flashes errors into the session. These errors are then automatically shared with Inertia as page props, allowing them to be displayed reactively on the client side.

--------------------------------

### Optimistic Updates > Router Visits

Source: https://inertiajs.com/docs/v3/the-basics/optimistic-updates

When the server responds, Inertia replaces the optimistic data with the actual server response. If the request encounters an error, the props are automatically reverted to their original state, ensuring data consistency.

--------------------------------

### Server-Side Rendering (SSR) > Deployment

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

The `inertia:check-ssr` Artisan command can be used to verify that the SSR server is operational, serving as a useful tool for post-deployment checks and as a Docker health check. By default, it ensures the server-side bundle exists before sending a request. This check can be disabled if your web server lacks access to the SSR bundle, for instance, in multi-server or containerized setups.

--------------------------------

### Custom Headers

Source: https://inertiajs.com/docs/v3/the-basics/links

The `headers` prop allows you to include custom headers in an Inertia link. However, Inertia's internal headers for state communication take precedence and cannot be overridden.

--------------------------------

### Load When Visible

Source: https://inertiajs.com/docs/v3/data-props/load-when-visible

Inertia supports lazy loading data on scroll using the Intersection Observer API. It provides the `WhenVisible` component as a convenient way to load data when an element becomes visible in the viewport. The `WhenVisible` component accepts a `data` prop that specifies the key of the prop to load and a `fallback` prop for a component to render while data is loading. This component should wrap the component that depends on the data.

--------------------------------

### Server-Side Setup

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

Inertia requires a server-side framework configuration as the first step of installation. While Inertia provides an official adapter for Laravel, other frameworks are supported through community-maintained adapters.

--------------------------------

### Polling

Source: https://inertiajs.com/docs/v3/data-props/polling

For more granular control over polling, the poll helper offers `start` and `stop` methods. To prevent automatic polling upon component mount, set the `autoStart` option to `false` when initializing the poll helper.

--------------------------------

### Forms > Canceling Form Submissions

Source: https://inertiajs.com/docs/v3/the-basics/forms

To cancel an ongoing form submission, you can utilize the `cancel()` method. This is helpful in scenarios where a user might want to abort an action before it completes.

--------------------------------

### Options > File Validation

Source: https://inertiajs.com/docs/v3/the-basics/forms

By default, file inputs are excluded from validation requests to prevent unnecessary data uploads. If your application requires validation for file uploads, you can explicitly enable this functionality.

--------------------------------

### Multipart Limitations

Source: https://inertiajs.com/docs/v3/the-basics/file-uploads

Uploading files using `multipart/form-data` requests is not natively supported by some server-side frameworks for `PUT`, `PATCH`, or `DELETE` HTTP methods. A common workaround is to use a `POST` request for file uploads instead.

--------------------------------

### Client-Side Setup

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

After configuring your server-side framework, you need to set up your client-side framework. Inertia currently provides support for React, Vue, and Svelte.

--------------------------------

### Multipart Limitations

Source: https://inertiajs.com/docs/v3/the-basics/file-uploads

Frameworks like Laravel and Rails offer form method spoofing to handle file uploads. This feature allows you to use a `POST` request for uploading files while the framework interprets it as a `PUT` or `PATCH` request by including a `_method` attribute in the request data.

--------------------------------

### Clustering

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

The cluster option can be passed to the createServer function to initiate multiple Node server instances on a single port. This configuration distributes incoming requests across multiple threads using a round-robin approach to improve performance.

--------------------------------

### Reloading Indicator

Source: https://inertiajs.com/docs/v3/data-props/deferred-props

The `Deferred` component provides a `reloading` boolean through its slot when deferred props are being reloaded via a partial reload. This feature enables the display of a loading indicator while retaining the previously loaded data.

--------------------------------

### Remembering State > Saving Local State

Source: https://inertiajs.com/docs/v3/data-props/remembering-state

By using the `useRemember` feature with your local state, such as form data, Inertia will automatically save this data to the history state whenever it changes. This ensures that the data is restored correctly when the user navigates back through their browser history.

--------------------------------

### Wayfinder

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

When using Wayfinder, you can pass the resulting object directly to any router method. The router will infer the HTTP method and URL from the Wayfinder object.

--------------------------------

### Browser History

Source: https://inertiajs.com/docs/v3/the-basics/links

The `replace` prop controls browser history behavior. By default, visits push new history states. Setting `replace` to `true` will replace the current history state instead of adding a new one.

--------------------------------

### Precognition

Source: https://inertiajs.com/docs/v3/the-basics/forms

The Form component provides built-in support for Laravel Precognition, which allows for real-time form validation. This feature enables developers to perform validation without needing to duplicate server-side validation rules on the client side.

--------------------------------

### Customizing Transitions

Source: https://inertiajs.com/docs/v3/the-basics/view-transitions

View transitions can be customized using CSS. The View Transitions API provides pseudo-elements that allow you to target specific elements for custom animations. This enables fine-grained control over the transition effects.

--------------------------------

### Merging Props > Matching Items

Source: https://inertiajs.com/docs/v3/data-props/merging-props

When merging arrays, you can use the `matchOn` parameter to match existing items by a specific field and update them instead of appending new ones. Inertia will iterate over the specified array and attempt to match each item by the given field. If a match is found, the existing item will be replaced; otherwise, the new item will be appended.

--------------------------------

### Partial Reloads

Source: https://inertiajs.com/docs/v3/the-basics/links

Partial reloads allow you to specify that only a subset of a page's data should be retrieved from the server on subsequent visits. This optimization reduces the amount of data transferred by fetching only the necessary props.

--------------------------------

### Error Bags

Source: https://inertiajs.com/docs/v3/the-basics/validation

When multiple forms exist on a single page, validation errors for fields with identical names can cause conflicts where an error intended for one form appears in another. Error bags solve this by scoping validation errors to a unique key specific to a particular form, ensuring that errors are displayed only where they belong.

--------------------------------

### Authentication

Source: https://inertiajs.com/docs/v3/security/authentication

Inertia.js eliminates the need for specialized authentication systems like OAuth because data is provided directly via server-side controllers. Since the JavaScript components and the data provider reside on the same domain, developers do not need to configure Cross-Origin Resource Sharing (CORS).

--------------------------------

### Code Splitting > Vite Plugin

Source: https://inertiajs.com/docs/v3/advanced/code-splitting

When using the Inertia Vite plugin, the lazy loading behavior is controlled by the lazy option within the pages shorthand, which is enabled by default.

--------------------------------

### Instant Visits

Source: https://inertiajs.com/docs/v3/the-basics/instant-visits

Instant visits allow Inertia to immediately swap to the target page component while the server request happens in the background. Once the server responds, the real props are merged in. Unlike client-side visits, which update the page entirely on the client without making a server request, instant visits still make a full server request. The difference is that the user sees the target page right away instead of waiting for the response.

--------------------------------

### Manually Saving State

Source: https://inertiajs.com/docs/v3/data-props/remembering-state

While the useRemember hook provides automatic state synchronization, developers can also manage history state manually. The router provides remember and restore methods to explicitly save and retrieve local component state from the browser history.

--------------------------------

### Custom HTTP Client

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

You can provide a completely custom HTTP client using the `http` option for full control over how requests are made. This custom client must implement a `request` method. The `request` method accepts an `HttpRequestConfig` object and is expected to return a promise that resolves to an `HttpResponse`.

--------------------------------

### Event Callbacks

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

It is possible to return a promise from the `onSuccess()` and `onError()` callbacks. When a promise is returned, the 'finish' event will be delayed until the promise has successfully resolved, allowing asynchronous operations to complete before the visit concludes.

--------------------------------

### Validation > How It Works

Source: https://inertiajs.com/docs/v3/the-basics/validation

Inertia identifies validation errors by checking the page.props.errors object. If errors are detected, the request's onError callback is triggered instead of the onSuccess callback. This mechanism ensures that the client-side application can respond appropriately to validation failures without needing to process 422 status codes.

--------------------------------

### TypeScript > Global Configuration

Source: https://inertiajs.com/docs/v3/advanced/typescript

You can globally configure Inertia's TypeScript types by augmenting the `InertiaConfig` interface within the `@inertiajs/core` module. This is typically done in a `global.d.ts` file. The `import` statement or `export {}` is crucial for this file to be recognized as a module, enabling declaration augmentation. Ensure your `tsconfig.json` includes `.d.ts` files.

--------------------------------

### Forms > Server-Side Validation

Source: https://inertiajs.com/docs/v3/the-basics/forms

Inertia's `<Form>` component and `useForm` helper automatically manage server-side validation errors. When your server returns validation errors, they become available in the `errors` object without extra setup. Inertia integrates validation error handling into its redirect-based flow, similar to traditional server-side form submissions but without full page reloads.

--------------------------------

### Manual Setup

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

A manual `setup` callback will prevent the Vite plugin from automatically handling Server-Side Rendering (SSR). If you use a manual setup, you should create a separate SSR entry point and configure your application for client-side hydration.

--------------------------------

### Manually Saving State

Source: https://inertiajs.com/docs/v3/data-props/remembering-state

Browser limitations on the frequency of history.replaceState calls can impact state persistence. To avoid lost updates, developers should avoid calling router.remember too frequently and consider debouncing or batching state updates in high-frequency scenarios.

--------------------------------

### Combining with Other Prop Types

Source: https://inertiajs.com/docs/v3/data-props/once-props

The once modifier is designed to be flexible and can be chained with other prop modifiers. It is compatible with deferred, merge, and optional props, allowing developers to combine these behaviors to suit specific data loading requirements.

--------------------------------

### Requirements > Framework Versions

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Inertia.js v3 introduces updated minimum requirements for server-side and client-side frameworks. The Laravel adapter now requires PHP 8.2 and Laravel 11. For frontend frameworks, the React adapter requires React 19, and the Svelte adapter requires Svelte 5, necessitating the use of Svelte 5 runes syntax.

--------------------------------

### Vite Plugin Setup > Update your build script

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

For production builds, update your `package.json` to include a script that builds both the client-side and SSR bundles. This ensures that both parts of your application are compiled for deployment.

--------------------------------

### Project > Setting New Default Values

Source: https://inertiajs.com/docs/v3/the-basics/forms

You can set the current form values as new defaults after a successful submission using the `setDefaultsOnSuccess` attribute on the `Form` component. This is useful for pre-filling forms with updated information.

--------------------------------

### Reverse Mode

Source: https://inertiajs.com/docs/v3/data-props/infinite-scroll

Reverse mode also enables automatic scrolling to the bottom on initial load. This behavior can be disabled by setting the `auto-scroll` prop to `false`.

--------------------------------

### Testing > End-to-end Tests

Source: https://inertiajs.com/docs/v3/advanced/testing

End-to-end testing tools like Cypress or Pest simulate real user interactions in the browser. While slower, they offer high confidence by testing the application at the user-facing layer, ensuring JavaScript code is executed and verified.

--------------------------------

### Who Is Inertia.js For?

Source: https://inertiajs.com/docs/v3/core-concepts/who-is-it-for

By using Inertia.js, developers gain the advantages of a client-side application and a modern SPA experience without the overhead of building and maintaining a separate API. This approach aims to significantly boost developer productivity.

--------------------------------

### Resetting Props

Source: https://inertiajs.com/docs/v3/the-basics/layouts

If you need to clear all dynamic layout props manually, you can use the resetLayoutProps function to revert the layout to its base state.

--------------------------------

### Partial Reloads > Except Certain Props

Source: https://inertiajs.com/docs/v3/data-props/partial-reloads

Alternatively, you can use the `except` visit option to specify which props the server should exclude from the reload. This option also takes an array of prop keys.

--------------------------------

### Blade Directives

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

You can customize the root element `id` when using the legacy Blade directives by passing a value to the directive, for example: `@inertia('custom-app-id')`.

--------------------------------

### Partial Reloads

Source: https://inertiajs.com/docs/v3/data-props/partial-reloads

Partial reloads allow you to re-fetch only a subset of data from the server when visiting the same page, which can be a performance optimization if some page data is allowed to become stale. Inertia automatically merges this partial data with the existing client-side data.

--------------------------------

### History Encryption > Opting in

Source: https://inertiajs.com/docs/v3/security/history-encryption

History encryption is an opt-in feature that can be enabled globally, per-request, or via middleware. Global encryption is configured through the application settings, with the ability to opt out on specific pages. Per-request encryption is handled by calling a specific method before returning a response, while middleware allows for applying encryption to groups of routes.

--------------------------------

### Forms

Source: https://inertiajs.com/docs/v3/the-basics/forms

The duration of the `recentlySuccessful` state can be customized using the `form.recentlySuccessfulDuration` option, with a default value of 2000 milliseconds.

--------------------------------

### HTTP Requests

Source: https://inertiajs.com/docs/v3/the-basics/optimistic-updates

The useHttp hook supports optimistic updates for requests that do not interact with standard page props. In this context, the optimistic callback updates the form's own data, and the system automatically reverts the data to its pre-request state if the operation fails.

--------------------------------

### Deferred Props

Source: https://inertiajs.com/docs/v3/data-props/deferred-props

Inertia's deferred props feature allows developers to defer the loading of specific page data until after the initial page render. This approach improves perceived performance by enabling the initial page to load as quickly as possible.

--------------------------------

### Programmatic Prefetching

Source: https://inertiajs.com/docs/v3/data-props/prefetching

You can prefetch data programmatically using the `router.prefetch` method, which accepts a URL and optional visit options. If the `cacheFor` option is not specified, it defaults to a 30-second cache duration.

--------------------------------

### Progress Indicators > Programmatic Access

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

Developers can manually control the progress indicator for non-Inertia requests, such as those made with Axios, by using the progress methods directly. These methods allow for starting, stopping, and updating the progress bar state programmatically.

--------------------------------

### Displaying Errors > Multiple Errors Per Field

Source: https://inertiajs.com/docs/v3/the-basics/validation

Inertia's Laravel adapter, by default, returns only the first validation error for each field. To receive all errors, set the `$withAllErrors` property to `true` in your middleware. This will cause each field to contain an array of error strings instead of a single string.

--------------------------------

### Deferred Props > Client Side

Source: https://inertiajs.com/docs/v3/data-props/deferred-props

The client-side Deferred component manages the display of deferred props by automatically waiting for the specified data to become available before rendering its children. It supports defining a fallback state to show while the data is being fetched.

--------------------------------

### Stale While Revalidate

Source: https://inertiajs.com/docs/v3/data-props/prefetching

The 'Stale While Revalidate' strategy allows for customizing cache behavior. By passing a tuple to the `cacheFor` prop, you define how long the cache is considered fresh and how long it can be served as stale data before a server fetch is necessary. The first value is the fresh period, and the second is the stale period.

--------------------------------

### Authorization

Source: https://inertiajs.com/docs/v3/security/authorization

Authorization in Inertia applications should be managed server-side using your framework's native authorization policies. Since Inertia page components do not have direct access to server-side authorization helpers, the recommended approach is to perform these checks on the server and pass the resulting boolean values as props to your components.

--------------------------------

### Asset Versioning > Cache Busting

Source: https://inertiajs.com/docs/v3/advanced/asset-versioning

Asset refreshing in Inertia relies on the assumption that a hard page visit will prompt your assets to reload. Inertia itself does not enforce this; it's usually achieved through cache-busting techniques, such as appending a version query parameter to asset URLs. Laravel's Vite integration handles this automatically, and for Laravel Mix, you can enable versioning in your `webpack.mix.js` file.

--------------------------------

### Once Props > Expiration

Source: https://inertiajs.com/docs/v3/data-props/once-props

The expiration of a once prop can be managed using the `until()` method. This method accepts a `DateTimeInterface`, `DateInterval`, or an integer representing seconds. After the specified expiration time has elapsed, the once prop will be refreshed on the next subsequent visit.

--------------------------------

### Precognition

Source: https://inertiajs.com/docs/v3/the-basics/forms

To implement Precognition, the server must be properly configured to support it. Once configured, the validate method can be called with a specific field name to trigger validation for that field. Developers can use the invalid helper to check for errors, the valid helper to confirm successful validation, and the validating state to indicate when a request is currently in progress.

--------------------------------

### Polling

Source: https://inertiajs.com/docs/v3/data-props/polling

Inertia provides a poll helper to simplify polling your server for new information on the current page. This helper automatically stops polling when the page is unmounted, reducing boilerplate code. The only required argument is the polling interval in milliseconds.

--------------------------------

### Once Props > Sharing Once Props

Source: https://inertiajs.com/docs/v3/data-props/once-props

Once props can be shared globally across your application using the `Inertia::share()` method. For added convenience, Inertia.js provides a `shareOnce()` method that achieves the same result. Both `as()`, `fresh()`, and `until()` methods can be chained onto the `shareOnce()` method for further customization.

--------------------------------

### Testing Inertia > Assertions

Source: https://inertiajs.com/docs/v3/advanced/testing

Inertia's testing methods automatically fail if you do not interact with at least one of the props in a scope. The etc method allows you to bypass this requirement, which is useful when working with unreliable data or when you want to keep your tests simple by ignoring specific data structures.

--------------------------------

### HTTP Client > Interceptors

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

The built-in XHR client supports interceptors that allow you to modify requests, inspect responses, or handle errors globally. These interceptors apply to all HTTP requests made by Inertia, including those from the router, form helpers, and HTTP hooks. Each interceptor method returns a cleanup function that can be used to remove the handler when it is no longer required.

--------------------------------

### Optimistic Updates > Router Visits

Source: https://inertiajs.com/docs/v3/the-basics/optimistic-updates

You can chain the `optimistic()` method before any router visit to enable optimistic updates. The callback function receives the current page props and should return a partial update to be applied instantly. This allows your component to re-render with new values before the request is even sent.

--------------------------------

### History Encryption > Clearing History

Source: https://inertiajs.com/docs/v3/security/history-encryption

Clearing the history state involves rotating the encryption key stored in session storage. Once the key is rotated, any attempt to decrypt the previous history state will fail, forcing Inertia to make a fresh request to the server for the page data. This can be triggered on the server side before returning a response or on the client side.

--------------------------------

### Once Props > Creating Once Props

Source: https://inertiajs.com/docs/v3/data-props/once-props

To create a once prop, utilize the `Inertia::once()` method when returning a response. This method accepts a callback function that generates the prop data. Once the client receives this prop, subsequent requests will bypass the callback execution and omit the prop from the response payload. The client retains the remembered once props only while navigating between pages that explicitly include them.

--------------------------------

### Changes > router.cancel() Replaced

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

The 'router.cancel()' method has been replaced by 'router.cancelAll()'. While the previous method only targeted synchronous requests, the new method cancels all request types by default, including asynchronous and prefetch requests. Options can be passed to 'router.cancelAll()' to restrict which request types are cancelled.

--------------------------------

### Stale While Revalidate

Source: https://inertiajs.com/docs/v3/data-props/prefetching

When a request is made within the fresh period, the cache is returned immediately. During the stale period, stale data is served while the cache is refreshed in the background. After the stale period, the cache is considered expired, and a regular server request is made.

--------------------------------

### Configuring Defaults

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

You can configure default settings for various Inertia.js features by passing a `defaults` object to `createInertiaApp()`. This allows you to customize options for forms, prefetching, and global visit settings without needing to specify them for every instance.

--------------------------------

### Routing > Generating URLs

Source: https://inertiajs.com/docs/v3/the-basics/routing

Since server-side route helpers are not available client-side, developers can pass generated URLs as props from the controller to the component. This ensures that named routes remain accessible within the frontend application.

--------------------------------

### Server-Side Rendering (SSR)

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Server-side rendering utilizes Node.js to render pages in a background process. Therefore, Node.js must be available on your server for SSR to function correctly. Inertia's SSR server requires Node.js version 22 or higher.

--------------------------------

### Excluding Fields

Source: https://inertiajs.com/docs/v3/the-basics/forms

Sensitive data such as passwords can be excluded from the browser's history state to prevent security issues. Excluding these fields also prevents browsers from triggering unwanted 'save password' prompts when values are written to the history state.

--------------------------------

### Form Component

Source: https://inertiajs.com/docs/v3/advanced/typescript

The `<Form>` component supports generic type parameters for type-safe slot props. In React, you can pass the generic directly. For Vue and Svelte, the `createForm` helper is used to create a typed form component. This generic provides autocompletion and type checking for properties like `errors`, `setError`, and `clearErrors` when referencing form fields.

--------------------------------

### Merging Props > Client Side Visits

Source: https://inertiajs.com/docs/v3/data-props/merging-props

You can also merge props directly on the client side without making a server request using client-side visits. Inertia provides prop helper methods that allow you to append, prepend, or replace prop values.

--------------------------------

### Server-Side Rendering (SSR) > Throwing on Error

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

To catch Server-Side Rendering (SSR) issues early, you can configure Inertia to throw an exception instead of silently falling back to client-side rendering. This is particularly useful during development and testing to identify SSR failures before they impact users. However, this option is not recommended for production environments, as it will result in error responses instead of graceful fallbacks.

--------------------------------

### Forms > Manual Form Submissions

Source: https://inertiajs.com/docs/v3/the-basics/forms

You can submit forms manually using Inertia's `router` methods, bypassing the `<Form>` component or `useForm` helper. This allows for direct control over form submissions through methods like `router.post()`.

--------------------------------

### Client Side Visits

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

Be aware that some browsers limit the number of `history.pushState()` and `history.replaceState()` calls within a short period. Inertia catches this error and logs it, but the state update will be lost. Avoid calling `router.push()` or `router.replace()` too frequently, and consider debouncing or batching updates in high-frequency scenarios.

--------------------------------

### CSRF Protection > Making Requests

Source: https://inertiajs.com/docs/v3/security/csrf-protection

Laravel automatically manages CSRF tokens for Inertia and Axios requests. When using Laravel, it is important to omit the csrf-token meta tag to ensure the token refreshes correctly.

--------------------------------

### Forms > Form Errors

Source: https://inertiajs.com/docs/v3/the-basics/forms

Form validation errors are available through the `errors` property. In Laravel-based Inertia applications, these errors are automatically populated when `ValidationException` is thrown, such as during a request validation.

--------------------------------

### Visit Cancellation

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

The `router.cancelAll()` method can be used to cancel all in-flight visits, including synchronous, asynchronous, and prefetch requests. Specific request types can be excluded from cancellation by passing an options object.

--------------------------------

### Merging Props > Merge Methods

Source: https://inertiajs.com/docs/v3/data-props/merging-props

To merge a prop instead of overwriting it, use the `Inertia::merge()` method when returning your response. This method will append new items to existing arrays at the root level by default, but you can change this behavior to prepend items instead. For more precise control, you can target specific nested properties for merging while replacing the rest of the object, and you can combine multiple operations and target several properties at once.

--------------------------------

### Redirects > 303 Response Code

Source: https://inertiajs.com/docs/v3/the-basics/redirects

When redirecting after a `PUT`, `PATCH`, or `DELETE` request, you must use a `303` response code. A `303` redirect is very similar to a `302` redirect; however, the follow-up request is explicitly changed to a `GET` request. If you're using one of our official server-side adapters, all redirects will automatically be converted to `303` redirects.

--------------------------------

### Precognition

Source: https://inertiajs.com/docs/v3/the-basics/forms

Precognition enables real-time validation for forms within Inertia.js. It requires server-side support to function correctly, and developers should ensure their backend is configured to handle Precognition requests.

--------------------------------

### Event Callbacks > HTTP exceptions

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

The onHttpException callback is triggered when the server returns a non-422 error, such as a 500 or 403 status code. It provides the response object to allow for inspection of the error status, body, and headers.

--------------------------------

### Scroll Preservation

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

Inertia automatically resets the scroll position on page navigation. This behavior can be disabled by setting the `preserveScroll` option to `true`. It can also be configured to preserve scroll only on validation errors by setting it to "errors", or lazily evaluated using a callback.

--------------------------------

### Forms > Programmatic Access

Source: https://inertiajs.com/docs/v3/the-basics/forms

In React and Vue, refs provide access to all form methods and reactive state. In Svelte, refs expose only methods, so reactive state like `isDirty` and `errors` should be accessed via slot props instead.

--------------------------------

### Optimistic Updates

Source: https://inertiajs.com/docs/v3/the-basics/optimistic-updates

Inertia allows for UI updates to be applied instantly without waiting for a server response. This is useful for actions like incrementing counters or toggling states. Optimistic updates apply changes immediately and automatically revert them if the server request fails.

--------------------------------

### Running the SSR Server

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

The SSR server is intended for production environments, as the Vite plugin manages SSR automatically during development. Once client-side and server-side bundles are built, the server is started via an Artisan command. The runtime environment, such as node or bun, can be configured globally in the Inertia configuration file or overridden via command-line flags.

--------------------------------

### Invalidate on Requests

Source: https://inertiajs.com/docs/v3/data-props/prefetching

To automatically invalidate caches when making requests, use the `invalidateCacheTags` prop on the `Form` component. The specified tags will be flushed when the form submission succeeds. This can also be configured within the visit options when using the `useForm` helper or with programmatic visits.

--------------------------------

### Removing Listeners

Source: https://inertiajs.com/docs/v3/advanced/events

When registering an event listener in Inertia, the registration process automatically returns a callback function. Invoking this returned function allows you to remove the specific event listener when it is no longer needed.

--------------------------------

### Changes > Axios Removed

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Inertia no longer includes Axios as a dependency. The library now utilizes a built-in XHR client that supports interceptors. Developers can continue using Axios via a dedicated adapter or implement a custom HTTP client if specific requirements exist.

--------------------------------

### Touch and Validate

Source: https://inertiajs.com/docs/v3/the-basics/forms

The touch method provides a way to mark form fields as having been interacted with by the user without immediately triggering validation logic. By calling the validate method without any arguments, developers can trigger validation specifically for all fields that have been previously marked as touched. Additionally, the touched helper can be used to check if any field has been interacted with, while the reset method clears the touched state for fields.

--------------------------------

### Client Side Visits

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

You can use the `router.push` and `router.replace` methods to make client-side visits. These methods are useful when you want to update the browser's history without making a server request.

--------------------------------

### Forms > Setting New Default Values

Source: https://inertiajs.com/docs/v3/the-basics/forms

You can update the default values of a form using the `defaults()` method. Calling `defaults()` without arguments sets the form's current values as the new defaults. You can also specify individual fields or an object of fields with their new default values.

--------------------------------

### HTTP Client

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

Inertia 3 features a built-in XHR client for all requests, eliminating the need for external HTTP libraries like Axios. However, you can still use Axios by providing the axiosAdapter as the http option when creating your Inertia app, which allows for the use of custom Axios instances.

--------------------------------

### Who Is Inertia.js For?

Source: https://inertiajs.com/docs/v3/core-concepts/who-is-it-for

Inertia.js enables the creation of modern, JavaScript-based SPAs while maintaining a development workflow similar to classic server-side rendered applications. Developers continue to create controllers, fetch data from databases using ORMs, and render views. However, these views are implemented as JavaScript page components using frameworks like React, Vue, or Svelte.

--------------------------------

### Maximum Response Size

Source: https://inertiajs.com/docs/v3/the-basics/responses

Inertia stores server responses in the browser's history state to facilitate client-side history navigation. Developers should be aware that browsers enforce specific size limits on the amount of data that can be saved in this state. Exceeding these limits can result in errors, such as the one encountered in Firefox when the 16 MiB threshold is surpassed. While these limits are generally high enough for most application requirements, they remain a technical constraint to consider during development.

--------------------------------

### Forms > Form Errors

Source: https://inertiajs.com/docs/v3/the-basics/forms

The `hasErrors` property can determine if a form contains any validation errors. The `clearErrors()` method is available to remove all errors or errors for specific fields.

--------------------------------

### Browser History

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

By default, Inertia adds a new entry to the browser history for every visit. You can override this behavior by setting the replace option to true, which replaces the current history entry instead of creating a new one. Note that visits made to the same URL automatically default to using the replace behavior.

--------------------------------

### useHttp > Validation Errors

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

When a request returns a `422` status code, the hook automatically parses validation errors and makes them available through the `errors` property.

--------------------------------

### TypeScript > Global Configuration > Error Values

Source: https://inertiajs.com/docs/v3/advanced/typescript

By default, validation error values in Inertia are typed as strings. You can configure the `errorValueType` option to expect arrays of strings, which is useful when your application supports multiple validation errors per field.

--------------------------------

### Testing > Client-Side Unit Tests

Source: https://inertiajs.com/docs/v3/advanced/testing

Client-side unit testing frameworks such as Vitest, Jest, or Mocha allow for isolated testing of JavaScript page components within a Node.js environment.

--------------------------------

### Changes > Dependency Removals

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

The 'qs' and 'lodash-es' packages have been removed as internal dependencies of @inertiajs/core. If your application relies on these libraries, you must install them directly as project dependencies to maintain functionality.

--------------------------------

### Server-Side Rendering (SSR) > Excluding Routes from SSR

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Inertia provides mechanisms to exclude specific routes from Server-Side Rendering (SSR) while keeping SSR enabled for the rest of your application. This can be achieved by defining patterns in your Inertia middleware, using the `Inertia::withoutSsr()` facade method, or by conditionally disabling SSR for the current request via configuration.

--------------------------------

### Prefetched

Source: https://inertiajs.com/docs/v3/advanced/events

The prefetched event is triggered once the router has successfully completed the prefetching of a page. This event is not cancelable.

--------------------------------

### CSRF Protection > Making Requests

Source: https://inertiajs.com/docs/v3/security/csrf-protection

You can customize the names of the XSRF cookie and header used by Inertia by configuring the http option within the createInertiaApp function.

--------------------------------

### State Preservation

Source: https://inertiajs.com/docs/v3/the-basics/manual-visits

The preserveState option can be configured to handle specific scenarios. Setting it to 'errors' ensures state is only preserved if the server response contains validation errors. Additionally, developers can provide a callback function to lazily evaluate whether state should be preserved based on the page response.

--------------------------------

### Polling

Source: https://inertiajs.com/docs/v3/data-props/polling

The poll helper includes throttling by default, reducing requests by 90% when the browser tab is in the background. To disable this behavior and keep polling active regardless of tab focus, set the `keepAlive` option to `true`.

--------------------------------

### Vite Plugin Setup > Configure Vite

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

To configure Vite for SSR, add the Inertia plugin to your `vite.config.js` file. The plugin automatically detects your SSR entry point. You can also explicitly configure SSR options such as the entry point, port, and clustering.

--------------------------------

### Finish

Source: https://inertiajs.com/docs/v3/advanced/events

The finish event fires once an XHR request has completed, regardless of whether the response was successful or unsuccessful. This event is particularly useful for tasks such as hiding loading indicators. It is not a cancelable event.

--------------------------------

### Other Changes > SSR in Development

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

SSR development is now integrated into the Vite plugin workflow. By running the standard development command, SSR functionality is handled automatically without the need for separate build steps or manual server management, which are now reserved for production environments.

--------------------------------

### Vite Plugin Setup > Clustering

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

To enhance performance and scalability, you can enable clustering for the SSR server. This allows multiple Node.js servers to run on the same port, distributing requests across different threads in a round-robin manner.

--------------------------------

### Precognition

Source: https://inertiajs.com/docs/v3/the-basics/forms

Once Precognition is enabled on a form, developers can trigger validation for specific fields using the validate method. Helper methods are available to check if a field is currently validating, if it contains errors, or if it has successfully passed validation.

--------------------------------

### Community Adapters

Source: https://inertiajs.com/docs/v3/installation/community-adapters

In addition to the officially supported Laravel adapter, the Inertia.js ecosystem includes a wide variety of community-built server-side adapters. These adapters allow developers to integrate Inertia.js with numerous frameworks and languages, including AdonisJS, Django, Flask, Go, Rails, Symfony, and many others.

--------------------------------

### Partial Reloads

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

Partial reloads allow you to request only a subset of props from the server when visiting the same page component. This technique serves as a performance optimization by reducing the amount of data transferred, though it assumes that some page data may become stale.

--------------------------------

### TypeScript > Using pnpm

Source: https://inertiajs.com/docs/v3/advanced/typescript

When using pnpm, `@inertiajs/core` is not directly accessible in `node_modules` due to its strict dependency isolation. To resolve this, you can either configure pnpm to hoist the package by adding `public-hoist-pattern[]=@inertiajs/core` to your `.npmrc` file and running `pnpm install`, or add `@inertiajs/core` as a direct dependency using `pnpm add @inertiajs/core`.

--------------------------------

### Displaying Errors

Source: https://inertiajs.com/docs/v3/the-basics/validation

Validation errors are made available client-side as page component props. When using first-party server adapters, the `errors` prop will automatically be available to your page, allowing you to conditionally display them based on their existence.

--------------------------------

### Redirects > External Redirects

Source: https://inertiajs.com/docs/v3/the-basics/redirects

Sometimes it's necessary to redirect to an external website, or even another non-Inertia endpoint in your app while handling an Inertia request. This can be accomplished using a server-side initiated `window.location` visit via the `Inertia::location()` method. The `Inertia::location()` method will generate a `409 Conflict` response and include the destination URL in the `X-Inertia-Location` header. When this response is received client-side, Inertia will automatically perform a `window.location = url` visit.

--------------------------------

### Prefetching

Source: https://inertiajs.com/docs/v3/advanced/events

The prefetching event fires at the moment the router begins the process of prefetching a page. This event is not cancelable.

--------------------------------

### Partial Reloads

Source: https://inertiajs.com/docs/v3/core-concepts/the-protocol

Partial reloads are strictly scoped to requests made to the same page component. If the destination component changes, such as when a user is redirected to a login page, the partial reload mechanism will not trigger.

--------------------------------

### Merging Props

Source: https://inertiajs.com/docs/v3/data-props/merging-props

Inertia overwrites props with the same name when reloading a page. However, you may need to merge new data with existing data instead, for example, when implementing a "load more" button for paginated results. The Infinite scroll component uses prop merging under the hood. Prop merging only works during partial reloads; full page visits will always replace props entirely.

--------------------------------

### Asset Versioning > Manual Refreshing

Source: https://inertiajs.com/docs/v3/advanced/asset-versioning

If you prefer to manage asset refreshing manually, you can disable Inertia's automatic asset versioning by returning a fixed value (like `null`) from the `version` method in the `HandleInertiaRequests` middleware. This allows you to notify users about new frontend versions by exposing the actual asset version as shared data. On the frontend, you can monitor this version property and display a notification when a new version is detected.

--------------------------------

### Handling Mismatches

Source: https://inertiajs.com/docs/v3/security/csrf-protection

A superior approach to handling CSRF mismatches is to redirect the user back to the previous page and provide a flash message indicating that the page has expired. This ensures the application returns a valid Inertia response, allowing the flash message to be displayed as a prop to the user instead of triggering an error modal.

--------------------------------

### Options > Debouncing

Source: https://inertiajs.com/docs/v3/the-basics/forms

Validation requests are automatically debounced to optimize performance. The initial request is sent immediately, while subsequent changes are delayed by a default period of 1500ms. This timeout duration can be adjusted to better suit specific application requirements.

--------------------------------

### Installation > Setup root template

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

By default, Inertia's Laravel adapter assumes your root template is named `app.blade.php`. You can change this default by using the `Inertia::setRootView()` method.

--------------------------------

### Event Callbacks > Network errors

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

The onNetworkError callback handles failures caused by connectivity issues, such as a lost internet connection. It receives the standard Error object for debugging purposes.

--------------------------------

### Precognition

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

Laravel Precognition can be integrated into the HTTP hook to enable real-time validation. Once enabled, developers gain access to methods for tracking validation state, such as checking if a field is valid or has been touched.

--------------------------------

### Installation > React Strict Mode

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

The React adapter for Inertia provides built-in support for React's Strict Mode. This can be enabled by setting the strictMode option to true within the application configuration.

--------------------------------

### Who Is Inertia.js For?

Source: https://inertiajs.com/docs/v3/core-concepts/who-is-it-for

Inertia.js is designed for development teams and individual developers who are accustomed to building server-side rendered applications with frameworks like Laravel, Ruby on Rails, Django, or Phoenix. It addresses the common challenge of transitioning from traditional server-rendered views to modern, JavaScript-based single-page application frontends without the typical complexities of building a separate API.

--------------------------------

### Options

Source: https://inertiajs.com/docs/v3/the-basics/forms

Validation requests are automatically debounced by default, with the first request firing immediately and subsequent changes debounced after a 1500ms timeout. This timeout can be customized.

--------------------------------

### Forms > Form Errors

Source: https://inertiajs.com/docs/v3/the-basics/forms

If you are performing client-side validation, you can manually set errors on the form using the `setError()` method. This method can set a single error for a field or multiple errors at once.

--------------------------------

### Resetting Props

Source: https://inertiajs.com/docs/v3/data-props/merging-props

Client-side prop resetting allows you to clear specific prop values before merging new data. This is particularly useful for scenarios like clearing search results when a user initiates a new query on a paginated list.

--------------------------------

### Disabling Shared Prop Keys

Source: https://inertiajs.com/docs/v3/the-basics/instant-visits

You can disable the sharedProps metadata key in the Inertia configuration file to omit the list of shared keys from the server response. While the server continues to resolve and include shared prop values, the client will be unable to identify which props to carry over during instant visits if this metadata is removed.

--------------------------------

### Cache Invalidation

Source: https://inertiajs.com/docs/v3/data-props/prefetching

For more granular cache control, `router.flushByCacheTags` allows you to remove cached responses based on their associated tags. This method accepts a single tag or an array of tags to invalidate.

--------------------------------

### Concurrent Updates

Source: https://inertiajs.com/docs/v3/the-basics/optimistic-updates

Inertia handles concurrent optimistic requests by tracking which props each update affects. Server responses will only overwrite a prop once the last optimistic request that modified it has successfully resolved.

--------------------------------

### Testing Inertia > Testing Deferred Props

Source: https://inertiajs.com/docs/v3/advanced/testing

The loadDeferredProps method is used to test deferred properties by performing a follow-up request to load them. You can load all deferred props or target specific groups by passing a group name or an array of group names as an argument.

--------------------------------

### Server-Side Rendering (SSR) > Deployment

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

For deployment on Laravel Cloud, utilize its native support for Inertia SSR, which simplifies the process of running the SSR server. Similarly, on Laravel Forge, you can enable SSR through a dedicated toggle in the site's application panel, which automatically configures the necessary daemon and deployment scripts.

--------------------------------

### Scroll Management > Scroll Preservation

Source: https://inertiajs.com/docs/v3/advanced/scroll-management

You can prevent the default scroll reset behavior by setting the preserveScroll option to true. Alternatively, you can set this option to 'errors' to only preserve the scroll position if the server response contains validation errors. It is also possible to dynamically determine whether to preserve scroll by providing a callback function that evaluates the page response.

--------------------------------

### Preserving Errors

Source: https://inertiajs.com/docs/v3/data-props/load-when-visible

By default, the WhenVisible component maintains validation errors during reloads by setting preserveErrors to true. This behavior ensures that user feedback is not lost when the component triggers a refresh, though this setting can be explicitly overridden using the params prop.

--------------------------------

### How It Works

Source: https://inertiajs.com/docs/v3/the-basics/optimistic-updates

When an optimistic update is triggered, the system snapshots only the changed keys, merges the callback's return value into the current data, and sends the request. Upon success, the server response replaces the optimistic data. If the request fails, the system uses the snapshotted keys to roll back the changes to their original state.

--------------------------------

### Installation

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

The @inertiajs/vite plugin is compatible with both Vite 7 and Vite 8. Installation involves adding the Inertia client-side adapter and the Vite plugin to your project dependencies.

--------------------------------

### Vite Plugin Setup > Development Mode

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

During development, the Vite plugin manages SSR automatically. You do not need to build your SSR bundle separately or start a dedicated Node.js server. Running the Vite dev server is sufficient, as it exposes a server endpoint for Laravel with HMR support.

--------------------------------

### Optimistic Updates

Source: https://inertiajs.com/docs/v3/the-basics/http-requests

Optimistic updates allow the application to apply data changes synchronously before the server responds. If the subsequent request fails, the data is automatically rolled back to its previous state.

--------------------------------

### Server-Side Rendering (SSR) > Disabling SSR

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

To fully disable Server-Side Rendering (SSR), you need to disable both the Vite plugin, which handles development and production builds, and the Laravel adapter, which manages rendering requests. This ensures that neither the build process nor the request handling relies on SSR.

--------------------------------

### Cache Invalidation

Source: https://inertiajs.com/docs/v3/data-props/prefetching

You can manually flush the prefetch cache using `router.flushAll` to clear all cached data, or `router.flush` to remove cache for a specific page. The `usePrefetch` hook also offers a `flush` method for the current page's cache.

--------------------------------

### Deferred Component Behavior (React)

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

The React Deferred component has been updated to prevent resetting to a fallback state during partial reloads. Existing content remains visible while new data is fetched, aligning React behavior with Vue and Svelte. A new reloading slot prop is available to facilitate the display of loading indicators during these updates.

--------------------------------

### Validation > Sharing Errors

Source: https://inertiajs.com/docs/v3/the-basics/validation

To make server-side validation errors available to the client, the server-side framework must share them via an errors prop. While first-party adapters like the Laravel adapter handle this automatically, developers using other frameworks may need to implement this sharing logic manually.

--------------------------------

### Removing Listeners

Source: https://inertiajs.com/docs/v3/advanced/events

By integrating event listener removal with component lifecycle hooks, you can ensure that listeners are automatically cleaned up when a component unmounts. This practice helps prevent memory leaks and unintended behavior in your application.

--------------------------------

### Automatic Cache Flushing

Source: https://inertiajs.com/docs/v3/data-props/prefetching

By default, Inertia does not automatically flush the prefetch cache on navigation. Cached data is only evicted when it expires based on the cache duration. To flush all cached data on every navigation, you can set up an event listener that calls `router.flushAll()` on 'navigate'.

--------------------------------

### Events > Cancelling Events

Source: https://inertiajs.com/docs/v3/advanced/events

Certain Inertia.js events, including `before`, `networkError`, and `httpException`, can be cancelled. This allows you to prevent Inertia's default actions. Similar to native browser events, an event is considered cancelled if any of its listeners call `event.preventDefault()`. For convenience, returning `false` from a listener registered with `router.on()` also cancels the event.

--------------------------------

### Automatic Rollback

Source: https://inertiajs.com/docs/v3/the-basics/optimistic-updates

Optimistic state is automatically reverted when validation errors (422) occur, preserving the errors. The original state is restored when the request fails for any other server error. If a new visit interrupts an in-flight request, the previous optimistic state is restored before the new optimistic update is applied.

--------------------------------

### Once Props > Refreshing from the Client

Source: https://inertiajs.com/docs/v3/data-props/once-props

Once props can also be refreshed from the client-side through a partial reload. When a once prop is explicitly requested by the client, the server will always resolve it, regardless of its cached state.

--------------------------------

### Precognition

Source: https://inertiajs.com/docs/v3/the-basics/forms

Form inputs only reflect a valid or invalid state after the input has changed and a response has been received from the server. Furthermore, calling the validation method will not trigger a network request unless the current field value differs from the initial data provided to the form.

--------------------------------

### Network Error

Source: https://inertiajs.com/docs/v3/advanced/events

The networkError event is triggered by unexpected XHR errors, such as network interruptions, or errors encountered while resolving page components. Developers can cancel this event to prevent the default error handling behavior and manage the error manually. Note that this event does not fire for 400 or 500 level HTTP responses or non-Inertia responses.

--------------------------------

### Once Props > Forcing a Refresh

Source: https://inertiajs.com/docs/v3/data-props/once-props

You can force a once prop to be refreshed, overriding the client-side caching, by using the `fresh()` method. This method can be chained directly onto the `Inertia::once()` call. Additionally, the `fresh()` method accepts a boolean argument, which allows for conditional refreshing of the prop based on specific criteria.

--------------------------------

### Deferred Props > Server Side

Source: https://inertiajs.com/docs/v3/data-props/deferred-props

On the server side, props can be deferred by wrapping the data retrieval in a callback executed in a separate request. By default, all deferred props are fetched in a single subsequent request, but they can also be grouped to fetch data in parallel by assigning them to specific group names.

--------------------------------

### Combining with Deferred Props

Source: https://inertiajs.com/docs/v3/data-props/merging-props

Deferred props can be combined with mergeable props to delay the loading of data while ensuring that the data is marked as mergeable once it has been successfully resolved.

--------------------------------

### Disabling SSR During Tests

Source: https://inertiajs.com/docs/v3/advanced/testing

While SSR is often enabled for development and production environments, it is frequently unnecessary during testing. You can disable SSR by setting the INERTIA_SSR_ENABLED environment variable to false within your phpunit.xml file to prevent the Laravel adapter from dispatching SSR requests.

--------------------------------

### Disabling SSR During Tests

Source: https://inertiajs.com/docs/v3/advanced/testing

You can programmatically disable SSR by using the Inertia::disableSsr() method, which is commonly placed in a test base class to ensure it applies to all tests. This method also accepts a boolean or a closure, allowing for conditional disabling of SSR, which is particularly useful when called from a service provider.

--------------------------------

### Server-Side Rendering (SSR) > Disabling SSR

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

You can programmatically prevent the Laravel adapter from dispatching SSR requests using the `Inertia::disableSsr()` method. This is beneficial for scenarios where you want to maintain SSR in your build but disable it for specific situations, such as during automated tests or in particular deployment environments. The method accepts a boolean or a closure for conditional disabling.

--------------------------------

### Handling Mismatches

Source: https://inertiajs.com/docs/v3/security/csrf-protection

When a CSRF token mismatch occurs, server-side frameworks typically throw an exception resulting in an error response, such as a 419 status code. Because this is not a valid Inertia response, the default behavior is to display an error modal to the user, which can negatively impact the user experience.

--------------------------------

### Opting Out of the Vite Plugin

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Developers can opt out of the automatic SSR handling provided by the Inertia Vite plugin by setting the ssr option to false. When doing so, the developer assumes responsibility for managing the SSR build process and must explicitly configure the Laravel Vite plugin to recognize the SSR entry point.

=== COMPLETE CONTENT === This response contains all available snippets from this library. No additional content exists. Do not make further requests.