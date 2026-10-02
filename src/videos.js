/* Films: poster asset key -> Google Drive id of the web-sized [SMALL] mp4 (Drive refuses cross-site playback, so these are the SOURCE files to re-encode and host).
   The page plays them through videoURL(). Generated from the Drive listing; see tools/ for the mapping rules. */
const VIDEOS = {
 "f-01": "123uep-xby_bQH6I-xo3feeBktSrn5nzk",
 "f-02": "13t2VYMjZCJDHa9XOwqFXrXwDG-nw60U0",
 "f-03": "11UiZiol3CWLv4GEJWSUvYeZE4bMUSZnC",
 "film-palau-1": "1nyJAPKRNu-L8QreWxRwodax1RLBaviou",
 "film-palau-2": "1poUOnx-b7QpVAnx0X5sFmjvQdjF0wOvm",
 "film-palau-3": "1Ye1fQ73d6NFlhYT6JE9Ci-Ir_sFMEqJF",
 "film-bangkok-1": "1Jh57gJzQAJb5buwqOZXA1fGfxYt8vpIc",
 "film-bangkok-2": "1iDREAQNi9z-osc_UiBbZRez_TNLiMSkD",
 "film-heritance-1": "1rQlsmVWglM-ekSUGQrVqW5FcopCa0T-x",
 "film-heritance-2": "1n98OFofjKSYXT7lbVyft7fS0n5lvZZOv",
 "film-heritance-3": "1QaRQUZ6bjPyU2DiMSja3mSv9i-Ki8Idb",
 "film-hideaway-1": "16bdZyPQQQ4GRyH5eImviTnjxhsx-iFIW",
 "film-intercontinental-1": "17jfO47x_G-IEmIHHcFocTgSeczASagjL",
 "film-intercontinental-2": "1zMbCMWyiOwVqEKYnKVyCZNQWxkJmydsg",
 "film-intercontinental-3": "1Baz_2Qe-n16FJ3LuXogpN-FFogjsceDS",
 "film-westin-1": "1oGC9g0iQX-id_RpiAowx0iUPk9ia-RYA",
 "film-westin-2": "11YxGuGSc3F3Ac_OCg9F3x_SsDNzLWEBG",
 "film-westin-3": "1peGqPxFqJRNHaxqtFYYQ__SbeZNWdgYp",
 "film-italy-1": "145jDsEmmPwS9iq0-2ErTQZXszV3cYqW5",
 "film-italy-4": "1fFlwBFeD8pH7PBlkMdUxDgLGTxIMmtoZ",
 "film-mazda-1": "1Ftg8lphXzVQnJQyjQq4m0dvTfwrbqfeM",
 "film-garmin-2": "1ZNE7Tglvg8CDX8ZLMeEGMMbqjEoV6cX_",
 "film-dior-1": "1D7o2wB2f_dvOcjPR_lNxtGFmLtnkEd6S",
 "pl-hoiana": "1qUUDIYmsEUKxZq1Ob6wNJ-zxUuB-JolE",
 "pl-hoiana-2": "16Kq0_4T2C3Z-wBiOZwG-syvvFx-WKVAs",
 "pl-millennium": "12r0oh5uiRXg0KEmrSlJMDXU894T8P260",
 "pl-millennium-2": "1VWQ8wNgE3BNNclINNp2MImQ7w_YJf40Y",
 "pl-sixsenses": "1IXvxJ6VJErHds4wJYeUae_ZqLBfOiiTq",
 "pl-sixsenses-2": "1QvCBoT7H512bXoTitIXzccv8MXFU8xHI",
 "hero-wide": "1iu-3VVd_oKZ-cvIS8tZCARrE4ZQBbC_h",
 "hero-m": "13P3Bn-ti-XY5RMdTh9mI4I1y0wEbnQCe"
};
// Web versions (720p H.264, tools/encode_videos.sh) live on the client's server. On the server itself they are same-origin;
// every other build (prototype, test deploy) streams them from there (CORS is open for .mp4 in .htaccess).
const VIDEO_BASE = (window.SITE_DATA && window.SITE_DATA.routing === 'path') ? '/assets/video' : 'https://www.chasingstories.org/assets/video';
const videoURL = k => (VIDEO_BASE && VIDEOS[k]) ? `${VIDEO_BASE}/${k}.mp4` : '';
