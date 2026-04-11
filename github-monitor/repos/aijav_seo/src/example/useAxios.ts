import axios from "axios";
import { useRef, useState } from "react";
import u from "./utils";
import { INSNAME } from "./constant";
import Cookies from "universal-cookie";

const cookies = new Cookies();

// const lang = localStorage.getItem("curr_lang") || "zh";
const lang = cookies.get("curr_lang") || "zh";

axios.defaults.headers["suffix"] = "NWSdef";
axios.defaults.headers["lang"] = lang;
// axios.defaults.headers["Content-Type"] = "application/x-www-form-urlencoded";

const useAxios = (url: string, method: string, encrypt = true) => {
  const [error, setError] = useState("");
  const baseUrl = "/api";
  const controllerRef = useRef(new AbortController());

  const req = async (data?: any) => {
    let payload = {} as any;

    const subdomain = u.checkSubdomain();
    if (subdomain) {
      switch (subdomain) {
        case INSNAME.WUMA:
          payload["site"] = 2;
          break;
        case INSNAME.DM:
          payload["site"] = 3;
          break;
        case INSNAME["4K"]:
          payload["site"] = 4;
          break;
        default:
          payload["site"] = 1;
          break;
      }
    }
    payload["device"] = u.isMobile() ? 1 : 2;

    payload["timestamp"] = new Date().getTime();
    payload = { ...payload, ...data };

      console.log(`${url}`, payload);

      if (encrypt) {
      payload["encode_sign"] = u.base64Sign(payload);
      payload = { "post-data": u.encrypt(JSON.stringify(payload)) };
    }


      try {
          const response = await axios.request({
        url: `${baseUrl}/${url}`,
        data: payload,
        method,
        signal: controllerRef.current.signal,
        // __data: payload,
        // __endpoint: url,
      } as any);
      let __data = u.decrypt(response.data.data, response.data.suffix);

        console.log(url);
        __data = JSON.parse(__data);

        console.log(__data);
        response.data.data = __data;

      if (response.data.data.code === 2000) {
        u.removeTokens();
      }

      return response.data;
    } catch (err: any) {
      setError(err.message);
    }
  };

  return { req, error };
};

export default useAxios;
