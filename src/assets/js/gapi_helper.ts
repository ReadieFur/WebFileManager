//https://developers.google.com/identity/gsi/web/reference/js-reference
//So I write all of this and some extra things into directory.ts just to realise that I can't use half of it because GAPI has very locked down URLs.

export enum EGAPIStatus
{
    NOT_LOADED,
    LOADING,
    LOAD_FAILED,
    LOADED,
    IN_USE,
    FAILED
}

export interface IOneTapConfig extends google.IdConfiguration
{
    client_id: string;
}

export interface IPlatformJSConfig extends gapi.auth2.ClientConfig
{
    client_id: string;
}

export interface IButtonOptions
{
    scope?: string;
    width?: number;
    height?: number;
    longtitle?: boolean;
    theme?: "light" | "dark";
    onsuccess?(user: gapi.auth2.GoogleUser): void;
    onfailure?(reason: { error: string }): void
    app_package_name?: string,
}

export interface IRejectReason
{
    code: EGAPIStatus;
    error: any;
}

type TRejectReason = (reason: IRejectReason) => any;

export class GAPIHelper
{
    private static state: EGAPIStatus;
    private static oneTapData:
    {
        callback: google.IdConfiguration["callback"];
        nativeCallback: google.IdConfiguration["native_callback"];
        intermediateIFrameCloseCallback: google.IdConfiguration["intermediate_iframe_close_callback"];
    };
    private static platformJSConfig?: IPlatformJSConfig;

    public static async Init(): Promise<void>
    {
        if (this.state === EGAPIStatus.LOADED) { return Promise.resolve(); }
        if (this.state === EGAPIStatus.LOADING) { return Promise.reject({code: EGAPIStatus.LOADING, error: null}); }

        this.state = EGAPIStatus.LOADING;
        this.oneTapData =
        {
            callback: undefined,
            nativeCallback: undefined,
            intermediateIFrameCloseCallback: undefined
        };
        this.platformJSConfig = undefined;

        if (window.google === undefined)
        {
            await new Promise<void>((googleResolve, googleReject) =>
            {
                const platformJSScript = document.createElement("script");
                platformJSScript.onerror = (error) => { googleReject({code: EGAPIStatus.LOAD_FAILED, error: error}); };
                platformJSScript.onload = () => { googleResolve(); };
                platformJSScript.src = "https://accounts.google.com/gsi/client";
                document.head.appendChild(platformJSScript);
            }).catch((error) => { Promise.reject(error); });
        }

        if (window.gapi === undefined)
        {
            await new Promise<void>((pjsResolve, pjsReject) =>
            {
                const platformJSScript = document.createElement("script");
                platformJSScript.onerror = (error) => { pjsReject({code: EGAPIStatus.LOAD_FAILED, error: error}); };
                platformJSScript.onload = () => { pjsResolve(); };
                platformJSScript.src = "https://apis.google.com/js/platform.js";
                document.head.appendChild(platformJSScript);
            }).catch((error) => { Promise.reject(error); });

            await new Promise<void>((pjsInitResolve, pjsInitReject) =>
            {
                (<any>gapi).load("auth2", () => { pjsInitResolve(); });
            });
        }

        this.state = EGAPIStatus.LOADED;
        return Promise.resolve();
    }

    public static async OneTapPrompt(
        config: IOneTapConfig,
        momentListener?: (promptMomentNotification: google.PromptMomentNotification) => void
    ): Promise<void>
    {
        if (this.state != EGAPIStatus.LOADED) { return Promise.reject({code: this.state, error: null}); }
        else if (
            this.oneTapData.callback !== undefined ||
            this.oneTapData.nativeCallback !== undefined ||
            this.oneTapData.intermediateIFrameCloseCallback !== undefined
        ) { return Promise.reject({code: EGAPIStatus.IN_USE, error: null}); }

        this.oneTapData.callback = config.callback;
        this.oneTapData.nativeCallback = config.native_callback;
        this.oneTapData.intermediateIFrameCloseCallback = config.intermediate_iframe_close_callback;

        //@ts-expect-error I hate this UMD global issue.
        google.accounts.id.initialize(
        {
            ...config,
            //I can unassign these events after they have fired because they only fire once.
            callback: (credentialResponse) =>
            {
                if (this.oneTapData.callback !== undefined)
                {
                    this.oneTapData.callback(credentialResponse);
                    this.oneTapData.callback = undefined;
                }
            },
            native_callback: () =>
            {
                if (this.oneTapData.nativeCallback !== undefined)
                {
                    this.oneTapData.nativeCallback();
                    this.oneTapData.nativeCallback = undefined;
                }
            },
            intermediate_iframe_close_callback: () =>
            {
                if (this.oneTapData.intermediateIFrameCloseCallback !== undefined)
                {
                    this.oneTapData.intermediateIFrameCloseCallback();
                    this.oneTapData.intermediateIFrameCloseCallback = undefined;
                }
            }
        });

        try
        {
            //@ts-expect-error
            google.accounts.id.prompt(momentListener);
        }
        catch (ex)
        {
            Promise.reject({code: EGAPIStatus.FAILED, error: ex});
        }

        return Promise.resolve();
    }

    public static async ClearOneTapPrompt(): Promise<void>
    {
        if (this.state != EGAPIStatus.LOADED) { return Promise.reject({code: this.state, error: null}); }
        try
        {
            //@ts-expect-error
            google.accounts.id.clear();
        }
        catch (ex)
        {
            //Don't reject here.
            // Promise.reject({code: EGAPIStatus.FAILED, error: ex});
        }
        this.oneTapData.callback = undefined;
        this.oneTapData.nativeCallback = undefined;
        this.oneTapData.intermediateIFrameCloseCallback = undefined;
        return Promise.resolve();
    }

    public static async AttatchGAPISignInButton(
        element: HTMLElement,
        successCallback?: (user: gapi.auth2.GoogleUser) => void,
        failureCallback?: (reason: { error: string }) => void,
        options?: IPlatformJSConfig,
        timeout = 1000,
        interval = 100
    ): Promise<void>
    {
        if (this.state != EGAPIStatus.LOADED) { return Promise.reject({code: this.state, error: null}); }
        if (this.platformJSConfig === undefined && options === undefined) { return Promise.reject({code: EGAPIStatus.LOAD_FAILED, error: null}); }

        if (this.platformJSConfig != options)
        {
            this.platformJSConfig = options;
            await new Promise<void>((resolve) =>
            {
                gapi.auth2.init({...options}).then(() => { resolve(); })
            });
        }

        const tmpContainer = document.createElement("div"); //Create a temporary container to hold the html without removing it from the DOM/memory so any events attatched are still valid.
        for (const child of element.children) { tmpContainer.appendChild(child); } //Move the children.
        element.innerHTML = ""; //Clear the original element for GAPI.

        const elementIDWasNull = element.id === "";
        if (elementIDWasNull)
        {
            var elementID: string;
            do
            {
                elementID = "";
                const characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz"; //Removed numbers just to make it easier to not encounter a number as the first character.
                for (let i = 0; i < 8; i++) { elementID += characters.charAt(Math.floor(Math.random() * characters.length)); }
            } while (document.querySelector(`#${elementID}`) !== null);
            element.id = elementID;
        }
        
        //This method is asyncrhonous I think so I should add a timeout to keep checking if the element did get rendered.
        gapi.signin2.render(element.id,
        {
            onsuccess: successCallback,
            onfailure: failureCallback,
        });

        var gapiHTMLElement: HTMLDivElement | null = null;
        const checksBeforeFailure = timeout / interval;
        for (let i = 0; i < checksBeforeFailure; i++)
        {
            const abcRioButton: HTMLDivElement | null = element.querySelector(".abcRioButton");
            gapiHTMLElement = abcRioButton;
            if (abcRioButton !== null) { break; }
            await new Promise((_resolve) => setTimeout(_resolve, interval));
        }
        if (elementIDWasNull) { element.id = ""; } //Reset the element ID if it was not originally set.
        if (gapiHTMLElement === null)
        {
            //The element didn't get rendered, so move the original children back and return false.
            for (const child of tmpContainer.children) { element.appendChild(child); }
            return Promise.reject({code: EGAPIStatus.FAILED, error: null});
        }

        if (gapiHTMLElement === null)
        {
            //The element didn't get rendered, so move the original children back and return false.
            for (const child of tmpContainer.children) { element.appendChild(child); }
            return Promise.reject({code: EGAPIStatus.FAILED, error: null});
        }

        //Remove the attributes that the gapi element sets by default.
        gapiHTMLElement.removeAttribute("class");
        gapiHTMLElement.removeAttribute("style");
        gapiHTMLElement.innerHTML = ""; //Clear the HTML that GAPI adds.

        //In the future I would like to try and replace all of the elements back onto the parent object with the GAPI elements events.
        for (const child of tmpContainer.children) { gapiHTMLElement.appendChild(child); } //Move the children back into the new parent container.

        return Promise.resolve();
    }

    public static async RenderSignInButton(
        elementID: string,
        uiOptions?: IButtonOptions,
        options?: IPlatformJSConfig
    )
    {
        if (this.state != EGAPIStatus.LOADED) { return Promise.reject({code: this.state, error: null}); }
        if (this.platformJSConfig === undefined && options === undefined) { return Promise.reject({code: EGAPIStatus.LOAD_FAILED, error: null}); }

        if (this.platformJSConfig != options)
        {
            this.platformJSConfig = options;
            await new Promise<void>((resolve) =>
            {
                gapi.auth2.init({...options}).then(() => { resolve(); })
            });
        }
        
        gapi.signin2.render(elementID, uiOptions ? uiOptions : {});
        return Promise.resolve();
    }

    public static SignOut(): Promise<void>
    {
        if (this.state != EGAPIStatus.LOADED) { return Promise.reject({code: this.state, error: null}); }
        return (<Promise<void>>gapi.auth2.getAuthInstance().signOut());
    }
}