import { Main, EErrorMessages } from "../../assets/js/main.js";
import { IFile } from "../directory/directory.js";

declare var PHP_DATA: string;

class File
{
    private static PHP_DATA: IPHP_DATA;

    private fileName?: HTMLHeadingElement;
    private contentContainer?: HTMLSpanElement;

    constructor()
    {
        new Main();

        File.PHP_DATA = JSON.parse(PHP_DATA);

        if (window.self !== window.top) //Check if the page is in an iframe
        {
            document.body.classList.add("inIFrame");
        }

        if (File.PHP_DATA.error !== undefined)
        {
            Main.Alert(Main.GetErrorMessage(File.PHP_DATA.error));
            return;
        }

        this.fileName = Main.GetElement("#fileName");
        this.contentContainer = Main.GetElement("#contentContainer");

        //While I could've set the size in PHP, to save rewriting the same code, I'll do it here.
        this.fileName.innerText += " | " + Main.FormatBytes(File.PHP_DATA.data!.size);

        const urlPartsToRemove = Main.WEB_ROOT.split("/").filter(n => n).length + 2; // +2 for .../view/file/
        const urlParts = window.location.pathname.split("/").filter(n => n).slice(urlPartsToRemove);
        if (urlParts.length === 0)
        {
            Main.Alert(Main.GetErrorMessage(EErrorMessages.INVALID_PATH));
            return;
        }

        switch (File.PHP_DATA.data!.mimeType.split("/")[0])
        {
            // case "video":
            //     break;
            // case "image":
            //     break;
            // case "audio":
            //     break;
            case "text":
                Main.XHR<string>(
                {
                    url: Main.WEB_ROOT + "/api/v1/file/" + urlParts.join("/"),
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
                    this.contentContainer!.appendChild(pre);
                }).catch(err => { Main.Alert("Error loading content."); });
                break;
            default:
                break;
        }
    }
}
new File();

interface IPHP_DATA
{
    error?: string;
    data?: IFile;
}