import SHA256 from "crypto-js/sha256";
import Cookies from "universal-cookie";
import { COOKIE_COMIC_STATISTICS } from "../constant";

const cookies = new Cookies();

const BASE_URL = "https://mtj.ast1930.com/";
const SECRET_KEY = "L2eRkBpQbPxNqRJw6M7dOkWsJvqpN8a5";

/**
 * Call backend API with signed payload
 * @param endpoint The API endpoint, e.g. "/api/recharge"
 * @param payload Base payload (must include amount, channel, status for signing)
 * @returns Parsed response from server
 */
async function comicStatistics(
  endpoint: string,
  payload: Record<string, any>
): Promise<any> {
  const query = new URLSearchParams(window.location.search);

  const queryChannel = query.get("channel");
  const cookiesChannel = cookies.get(COOKIE_COMIC_STATISTICS);

  if (queryChannel && !cookiesChannel) {
    const expires = new Date(Date.now() + 24 * 60 * 60 * 1000); // 1 day in ms
    cookies.set(COOKIE_COMIC_STATISTICS, queryChannel, {
      path: "/",
      expires: expires,
    });
  }

  const channel = queryChannel || cookiesChannel;
  if (!channel) {
    return;
  }

  const timestamp = Math.floor(Date.now() / 1000);
  const dataToSign: Record<string, any> = {
    ...payload,
    channel,
    timestamp,
  };

  // Sort keys and build signStr
  const sortedKeys = Object.keys(dataToSign).sort(); // sort alphabetically
  const signParts = sortedKeys.map((key) => `${key}=${dataToSign[key]}`);
  signParts.push(`secret_key=${SECRET_KEY}`); // append secret_key last
  const signStr = signParts.join("&");
  // Generate SHA256 signature
  const sign = SHA256(signStr).toString();

  // Final payload to send
  const finalPayload = {
    ...dataToSign,
    sign,
  };

  // Make the API request
  const response = await fetch(`${BASE_URL}${endpoint}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(finalPayload),
  });

  if (!response.ok) {
    const errText = await response.text();
    throw new Error(`请求失败: ${response.status} - ${errText}`);
  }

  return await response.json();
}

export { comicStatistics };
