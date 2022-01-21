import { Main, EErrorMessages, IXHRReject, IServerErrorResponse } from "../../assets/js/main.js";
import { IFile } from "../directory/directory.js";

declare var PHP_DATA: string;

class File
{
    private bodyBuilt: boolean;

    private fileName!: HTMLHeadingElement;
    private downloadButton!: HTMLLinkElement;
    private notice!: HTMLHeadingElement;
    private contentContainer!: HTMLSpanElement;

    constructor()
    {
        new Main();
        this.bodyBuilt = false;
        this.AsyncInit();
    }

    private async AsyncInit()
    {
        const phpData: IPHP_DATA = JSON.parse(PHP_DATA);
        var fileData: IFileExtended;

        if (window.self !== window.top) //Check if the page is in an iframe
        {
            document.body.classList.add("inIFrame");
        }

        if (phpData.error !== undefined)
        {
            if (phpData.error === EErrorMessages.GOOGLE_AUTHENTICATION_REQUIRED)
            {
                if (Main.GOOGLE_CLIENT_ID !== null && Main.RetreiveCache("google_user_token") !== undefined)
                {
                    const url = `${Main.WEB_ROOT}/api/v1/file/${window.location.pathname.substring(Main.WEB_ROOT.length).split("/").filter(n => n).splice(2).join("/")}`;

                    const fileDetailsResponse = await Main.XHR<IFile>(
                    {
                        url: url,
                        method: "GET",
                        data:
                        {
                            uid: Main.RetreiveCache("uid"),
                            token: Main.RetreiveCache("token"),
                            google_user_token: Main.RetreiveCache("google_user_token"),
                            details: true
                        },
                        responseType: "json"
                    }).catch((error: IXHRReject<IServerErrorResponse>) => { return error; });

                    if ((fileDetailsResponse as IXHRReject<IServerErrorResponse>).error !== undefined)
                    {
                        Main.Alert(Main.GetErrorMessage((fileDetailsResponse as IXHRReject<IServerErrorResponse>).response.error));
                        this.BuildBody(true);
                        return;
                    }

                    fileData =
                    {
                        ...(<IFile>fileDetailsResponse.response),
                        url: `${url}?google_user_token=${Main.RetreiveCache("google_user_token")}`
                    };
                }
                else if (Main.GOOGLE_CLIENT_ID !== null)
                {
                    Main.Alert(`${Main.GetErrorMessage(EErrorMessages.GOOGLE_AUTHENTICATION_REQUIRED)}<br>Please go to the account page and press link google account.`);
                    this.BuildBody(true);
                    return;
                }
                else
                {
                    //This stage shouldnt be reached but is possible.
                    //If a share in the database gets set to use google and then the api key is removed this state could be reached.
                    Main.Alert(Main.GetErrorMessage(Main.GOOGLE_CLIENT_ID ? EErrorMessages.UNKNOWN_ERROR : EErrorMessages.GAPI_NOT_CONFIGURED));
                    this.BuildBody(true);
                    return;
                }
            }
            else
            {
                Main.Alert(Main.GetErrorMessage(phpData.error));
                this.BuildBody(true);
                return;
            }
        }
        else
        {
            fileData = phpData.data!;
        }

        this.BuildBody(false);

        this.fileName = Main.GetElement("#fileName");
        this.downloadButton = Main.GetElement("#downloadButton");
        this.notice = Main.GetElement("#notice");
        this.contentContainer = Main.GetElement("#contentContainer");

        this.notice.style.display = "block";
        this.notice.innerText = "Loading...";

        this.LoadContent(fileData);
    }

    private BuildBody(error: boolean): boolean
    {
        if (this.bodyBuilt) { return false; }

        if (!error)
        {
            const section = document.createElement("section");
            section.id = "pageTitleContainer";

            const div = document.createElement("div");
            div.classList.add("leftRight");

            const h4FileName = document.createElement("h4");
            h4FileName.id = "fileName";
            div.appendChild(h4FileName);

            const a = document.createElement("a");
            a.innerText = "Download";
            a.id = "downloadButton";
            a.classList.add("asButton");
            a.target = "_blank";
            div.appendChild(a);

            section.appendChild(div);

            const hr = document.createElement("hr");
            section.appendChild(hr);

            const br = document.createElement("br");
            section.appendChild(br);

            const span = document.createElement("span");
            span.id = "contentContainer";
            
            const h4Notice = document.createElement("h4");
            h4Notice.id = "notice";
            h4Notice.classList.add("center");

            document.body.appendChild(section);
            document.body.appendChild(span);
            document.body.appendChild(h4Notice);
        }
        else
        {
            const h4 = document.createElement("h4");
            h4.innerText = "Unable to get file.";
            h4.classList.add("center");
            document.body.appendChild(h4);
        }

        this.bodyBuilt = true;
        return true;
    }

    private LoadContent(fileData: IFileExtended): void
    {
        //While I could've set the size in PHP, to save rewriting the same code, I'll do it here.
        this.fileName.innerText = `${fileData.name}${fileData.extension !== undefined ? "." + fileData.extension : ""} | ${Main.FormatBytes(fileData.size)}`;
        this.downloadButton.href = fileData.url + "?download";

        switch (fileData.mimeType.split("/")[0])
        {
            case "video":
                const video = document.createElement("video");
                video.onloadeddata = () => { this.notice!.style.display = "none"; };
                video.controls = true;
                video.src = fileData.url;
                this.contentContainer.appendChild(video);
                break;
            case "image":
                const image = document.createElement("img");
                image.onload = () => { this.notice!.style.display = "none"; };
                image.src = fileData.url;
                this.contentContainer.appendChild(image);
                break;
            case "audio":
                const audio = document.createElement("audio");
                audio.onload = () => { this.notice!.style.display = "none"; };
                audio.controls = true;
                audio.src = fileData.url;
                this.contentContainer.appendChild(audio);
                break;
            case "text":
                Main.XHR<string>(
                {
                    url: fileData.url,
                    method: "GET",
                    data:
                    {
                        uid: Main.RetreiveCache("uid"),
                        token: Main.RetreiveCache("token")
                    },
                    responseType: "text"
                })
                .then((result) =>
                {
                    var pre = document.createElement("pre");
                    pre.innerText = result.response;
                    pre.classList.add("light");
                    this.contentContainer.appendChild(pre);
                    this.notice.style.display = "none";
                }).catch(() => { Main.Alert("Error loading content."); });
                break;
            default:
                this.notice.innerText = "No preview available.";
                break;
        }
    }
}
new File();

interface IPHP_DATA
{
    error?: string;
    data?: IFileExtended;
}

interface IFileExtended extends IFile
{
    url: string;
    thumbnail?: IFile;
}