//Modified from: https://raw.githubusercontent.com/kOFReadie/BSDP-Overlay/master/src/assets/js/main.ts

declare var WEB_ROOT: string;
declare var ACCENT: string;

export class Main
{
    public static WEB_ROOT: string;
    public static ACCENT:
    {
        R: number;
        G: number;
        B: number;
        HEX: string;
    };
    public static header: HTMLElement;
    public static footer: HTMLElement;
    public static urlParams: URLSearchParams;

    private static alertBoxContainer: HTMLDivElement;
    private static alertBoxText: HTMLParagraphElement;
    private static alertBoxTextBox: HTMLInputElement;

    private static readonly COOKIE_PREFIX = "wfm_";

    constructor()
    {
        // !   /\                   ,'|
        // o--'O `.                /  /
        //  `--.   `-----------._,' ,'
        //     \                ,--'
        //      ) )    _,--(    |
        //     /,^.---'     )/ \\
        //    ((   \\      ((   \\
        //     \)   \)      \)  (/
        // -What are you doing here?

        console.log(`
            !   /\\                   ,'|
            o--'O \`.                /  /
             \`--.   \`-----------._,' ,'
                \\                ,--'
                 ) )    _,--(    |
                /,^.---'     )/ \\\\
               ((   \\\\      ((   \\\\
                \\)   \\)      \\)  (/
            -What are you doing here?
        `);
        
        Main.WEB_ROOT = WEB_ROOT;
        Main.ACCENT =
        {
            R: parseInt(ACCENT.substring(1, 3), 16),
            G: parseInt(ACCENT.substring(3, 5), 16),
            B: parseInt(ACCENT.substring(5, 7), 16),
            HEX: ACCENT
        };
        Main.urlParams = new URLSearchParams(location.search);
        Main.header = Main.ThrowIfNullOrUndefined(document.querySelector("#header"));
        Main.footer = Main.ThrowIfNullOrUndefined(document.querySelector("#footer"));

        Main.alertBoxContainer = Main.ThrowIfNullOrUndefined(document.querySelector("#alertBoxContainer"));
        Main.alertBoxText = Main.ThrowIfNullOrUndefined(document.querySelector("#alerBoxText"));
        Main.alertBoxTextBox = Main.ThrowIfNullOrUndefined(document.querySelector("#alertBoxTextBox"));
        Main.alertBoxContainer.addEventListener("click", () => { Main.alertBoxContainer.style.display = "none"; });

        if (Main.RetreiveCache(`dark_mode`) != "false") { Main.DarkTheme(true); }
        else { Main.DarkTheme(false); }
        Main.ThrowIfNullOrUndefined(document.querySelector("#darkMode")).addEventListener("click", () =>
        {
            var cachedValue = Main.RetreiveCache(`dark_mode`);
            if (cachedValue == undefined || cachedValue == "false") { Main.DarkTheme(true); }
            else { Main.DarkTheme(false); }
        });

        this.HighlightActivePage();

        let staticStyles = document.createElement("style");
        staticStyles.innerHTML = `
            *
            {
                transition:
                    background 400ms ease 0s,
                    background-color 400ms ease 0s,
                    color 100ms ease 0s;
            }
        `;
        document.head.appendChild(staticStyles);
    }

    private HighlightActivePage(): void
    {
        let path = window.location.pathname.split("/").filter((el) => { return el != ""; });
        for (let i = 0; i < path.length; i++) { path[i] = path[i].replace("_", ""); }
        
        Main.ThrowIfNullOrUndefined(document.querySelector("#header")).querySelectorAll("a").forEach((element: HTMLLinkElement) =>
        {
            if (element.href == window.location.origin + window.location.pathname)
            {
                element.classList.add("accent");
                // let whyIsThisSoFarBack = element.parentElement?.parentElement?.parentElement;
                let whyIsThisSoFarBack = element.parentElement !== null ? element.parentElement.parentElement !== null ? element.parentElement.parentElement.parentElement : null : null;
                if (whyIsThisSoFarBack !== null || whyIsThisSoFarBack !== undefined)
                {
                    if (whyIsThisSoFarBack!.classList.contains("naviDropdown")) { whyIsThisSoFarBack!.firstElementChild!.classList.add("accent"); }
                }
            }
        });
    }

    public static ThrowIfNullOrUndefined(variable: any): any
    {
        if (variable === null || variable === undefined) { throw new TypeError(`${variable} is null or undefined`); }
        return variable;
    }

    public static GetElement<T>(query: string): T
    {
        return Main.ThrowIfNullOrUndefined(document.querySelector(query));
    }

    public static DarkTheme(dark: boolean): void
    {
        Main.SetCache(`dark_mode`, dark ? "true" : "false", 365);
        var darkButton: HTMLInputElement = Main.ThrowIfNullOrUndefined(document.querySelector("#darkMode"));
        var themeColours: HTMLStyleElement = Main.ThrowIfNullOrUndefined(document.querySelector("#themeColours"));
        if (dark) { darkButton.classList.add("accent"); }
        else { darkButton.classList.remove("accent"); }
        themeColours.innerHTML = `
            :root
            {
                --foregroundColour: ${dark ? "255, 255, 255" : "0, 0, 0"};
                --backgroundColour: ${dark ? "13, 17, 23" : "255, 255, 255"};
                --backgroundAltColour: ${dark ? "22, 27, 34" : "225, 225, 225"};
                --accentColour: ${Main.ACCENT.R}, ${Main.ACCENT.G}, ${Main.ACCENT.B};
            }
        `;
    }

    public static RetreiveCache(cookie_name: string): string | undefined
    {
        cookie_name = Main.COOKIE_PREFIX + cookie_name;
        var i, x, y, ARRcookies = document.cookie.split(";");
        for (i = 0; i < ARRcookies.length; i++)
        {
            x = ARRcookies[i].substr(0, ARRcookies[i].indexOf("="));
            y = ARRcookies[i].substr(ARRcookies[i].indexOf("=") + 1);
            x = x.replace(/^\s+|\s+$/g, "");
            if (x == cookie_name) { return unescape(y); }
        }
        return undefined;
    }

    //Time is time in days.
    public static SetCache(cookie_name: string, value: string, time: number, path: string = '/'): void
    {
        cookie_name = Main.COOKIE_PREFIX + cookie_name;
        var hostSplit = window.location.host.split(".");
        var domain = `.${hostSplit[hostSplit.length - 2]}.${hostSplit[hostSplit.length - 1]}`;
        var expDate = new Date();
        expDate.setDate(expDate.getDate() + time);
        document.cookie = `${cookie_name}=${value}; expires=${expDate.toUTCString()}; path=${path}; domain=${domain};`;
    }

    public static ThrowAJAXJsonError(data: any) { throw new TypeError(`${data} could not be steralised`); }

    public static async XHR<T>(data:
    {
        url: string,
        method: 'GET' | 'POST',
        data?: Dictionary<any>,
        headers?: Dictionary<string>,
        responseType?: 'json' | 'text'
    }): Promise<IXHRResolve<T>>
    {
        return new Promise<{xhr: XMLHttpRequest, response: T}>((resolve, reject) =>
        {
            if (data.responseType === undefined) { data.responseType = 'json'; }

            var params = new URLSearchParams();
            if (data.data !== undefined)
            {
                for (var pKey in data.data)
                {
                    //The toString() method used below will convert the values to a suitable URL-encoded string.
                    params.set(pKey, data.data[pKey]);
                }
            }

            var xhr = new XMLHttpRequest();
            xhr.open(
                data.method,
                data.url + (data.method == "GET" ? "?" + params.toString() : ""),
                true
            );

            if (data.headers !== undefined)
            {
                for (var hKey in data.headers)
                {
                    xhr.setRequestHeader(hKey, data.headers[hKey]);
                }
            }

            xhr.onreadystatechange = () =>
            {
                if (xhr.readyState == 4)
                {
                    if (xhr.status == 200)
                    {
                        try
                        {
                            resolve({
                                xhr: xhr,
                                response: data.responseType === "json" ? JSON.parse(xhr.responseText) : xhr.responseText
                            });
                        }
                        catch (e)
                        {
                            reject(<IXHRReject<any>>{
                                status: xhr.status,
                                statusText: xhr.statusText,
                                response: xhr.responseText,
                                error: e
                            });
                        }
                    }
                    else
                    {
                        try
                        {
                            reject(<IXHRReject<any>>{
                                status: xhr.status,
                                statusText: xhr.statusText,
                                response: data.responseType === "json" ? JSON.parse(xhr.responseText) : xhr.responseText,
                                error: null
                            });
                        }
                        catch (e)
                        {
                            reject(<IXHRReject<any>>{
                                status: xhr.status,
                                statusText: xhr.statusText,
                                response: xhr.responseText,
                                error: e
                            });
                        }
                    }
                }
            };

            xhr.send(data.method == "GET" ? null : params);
        });
    }

    public static PreventFormSubmission(form: HTMLFormElement): void
    {
        form.addEventListener("submit", (e) => { e.preventDefault(); });
    }

    //This is asyncronous as I will check if the user has dismissed the alert box in the future.
    public static async Alert(message: string/*, solidBackground = false*/): Promise<void>
    {
        if (Main.alertBoxTextBox != null && Main.alertBoxText != null && Main.alertBoxContainer != null)
        {
            console.log("Alert:", message);
            Main.alertBoxTextBox.focus();
            Main.alertBoxText.innerHTML = message;
            Main.alertBoxContainer.style.display = "block";
        }
    }

    public static Unfocus()
    {
        Main.alertBoxTextBox.focus();
    }

    public static Sleep(milliseconds: number): Promise<unknown>
    {
        return new Promise(r => setTimeout(r, milliseconds));
    }

    public static GetErrorMessage(error: any): string
    {
        switch (error)
        {
            case "INVALID_PATH":
                return "Invalid path.";
            case "NO_RESPONSE":
                return "No response.";
            case "METHOD_NOT_ALLOWED":
                return "Method not allowed.";
            case "DIRECT_REQUEST_NOT_ALLOWED":
                return "Direct request not allowed.";
            case "INVALID_PARAMETERS":
                return "Invalid parameters.";
            case "INVALID_ACCOUNT_DATA":
                return "Invalid account data.";
            case "PATH_ALREADY_EXISTS":
                return "Path already exists.";
            case "DATABASE_ERROR":
                return "Database error.";
            case "THUMBNAL_ERROR":
                return "Thumbnail error.";
            case "INVALID_FILE_TYPE":
                return "Invalid file type.";
            case "UNKNOWN_ERROR":
                return "Unknown error.";
            default:
                return `Unknown error.<br><small>${String(error)}</small>`;
        }
    }
}

export type Dictionary<ValueType> = { [key: string]: ValueType }

export interface IXHRResolve<T>
{
    xhr: XMLHttpRequest,
    response: T
}

export interface IXHRReject<T>
{
    status: number,
    statusText: string,
    response: T,
    error: any
}

export interface IServerErrorResponse
{
    error: string;
}

export enum EErrorMessages
{
    INVALID_PATH = "INVALID_PATH",
    NO_RESPONSE = "NO_RESPONSE",
    METHOD_NOT_ALLOWED = "METHOD_NOT_ALLOWED",
    DIRECT_REQUEST_NOT_ALLOWED = "DIRECT_REQUEST_NOT_ALLOWED",
    INVALID_PARAMETERS = "INVALID_PARAMETERS",
    INVALID_ACCOUNT_DATA = "INVALID_ACCOUNT_DATA",
    PATH_ALREADY_EXISTS = "PATH_ALREADY_EXISTS",
    DATABASE_ERROR = "DATABASE_ERROR",
    THUMBNAL_ERROR = "THUMBNAL_ERROR",
    INVALID_FILE_TYPE = "INVALID_FILE_TYPE",
    UNKNOWN_ERROR = "UNKNOWN_ERROR"
}